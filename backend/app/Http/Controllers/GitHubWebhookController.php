<?php

namespace App\Http\Controllers;

use App\Enums\ChangeEntryStatus;
use App\Jobs\SummarizePullRequest;
use App\Models\ChangeEntry;
use App\Models\PullRequest;
use App\Models\Repository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use JsonException;
use RuntimeException;
use Throwable;

class GitHubWebhookController extends Controller
{
    public function __invoke(Request $request, string $repository): JsonResponse
    {
        $repository = ltrim($repository, '0') ?: '0';
        $maxId = '9223372036854775807';

        if (strlen($repository) > strlen($maxId) || (strlen($repository) === strlen($maxId) && strcmp($repository, $maxId) > 0)) {
            return response()->json(['message' => 'Repository not found.'], 404);
        }

        $repository = Repository::query()->find($repository);

        if ($repository === null) {
            return response()->json(['message' => 'Repository not found.'], 404);
        }

        $rawBody = $request->getContent();
        $signature = $request->header('X-Hub-Signature-256');

        try {
            $expectedSignature = 'sha256='.hash_hmac('sha256', $rawBody, $repository->webhook_secret);
        } catch (Throwable $exception) {
            $this->reportFailure($exception);

            return response()->json(['message' => 'Unable to process webhook.'], 500);
        }

        if (! is_string($signature) || ! hash_equals($expectedSignature, $signature)) {
            return response()->json(['message' => 'Invalid webhook signature.'], 401);
        }

        try {
            $payload = json_decode($rawBody, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return response()->json(['message' => 'Invalid webhook payload.'], 400);
        }

        if (! is_array($payload)) {
            return response()->json(['message' => 'Invalid webhook payload.'], 400);
        }

        if ($request->header('X-GitHub-Event') === 'ping') {
            return response()->json(['message' => 'Webhook received.'], 200);
        }

        if (
            $request->header('X-GitHub-Event') !== 'pull_request'
            || ($payload['action'] ?? null) !== 'closed'
            || ($payload['pull_request']['merged'] ?? false) !== true
        ) {
            return response()->json(['message' => 'Webhook received.'], 200);
        }

        $deliveryId = $request->header('X-GitHub-Delivery');
        $validator = Validator::make([
            ...$payload,
            'delivery_id' => $deliveryId,
        ], [
            'delivery_id' => ['required', 'string', 'max:255'],
            'pull_request' => ['required', 'array'],
            'pull_request.number' => ['required', 'integer', 'min:1'],
            'pull_request.title' => ['required', 'string', 'max:255'],
            'pull_request.body' => ['nullable', 'string'],
            'pull_request.user.login' => ['required', 'string', 'max:255'],
            'pull_request.html_url' => ['required', 'url', 'max:2048'],
            'pull_request.merged_at' => ['required', 'date'],
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Invalid webhook payload.'], 422);
        }

        $pullRequest = $payload['pull_request'];

        try {
            $createdPullRequest = DB::transaction(function () use ($repository, $pullRequest, $deliveryId): ?int {
                $inserted = DB::table('pull_requests')->insertOrIgnore([
                    'repository_id' => $repository->id,
                    'github_pr_number' => $pullRequest['number'],
                    'title' => $pullRequest['title'],
                    'body' => $pullRequest['body'] ?? null,
                    'author' => $pullRequest['user']['login'],
                    'html_url' => $pullRequest['html_url'],
                    'merged_at' => $pullRequest['merged_at'],
                    'github_delivery_id' => $deliveryId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                if ($inserted === 0) {
                    return null;
                }

                $pullRequestId = DB::table('pull_requests')
                    ->where('github_delivery_id', $deliveryId)
                    ->value('id');

                ChangeEntry::query()->create([
                    'pull_request_id' => $pullRequestId,
                    'status' => ChangeEntryStatus::PendingAi,
                ]);

                return (int) $pullRequestId;
            });

            if ($createdPullRequest !== null) {
                SummarizePullRequest::dispatch(PullRequest::query()->findOrFail($createdPullRequest));
            }
        } catch (Throwable $exception) {
            $this->reportFailure($exception);

            return response()->json(['message' => 'Unable to process webhook.'], 500);
        }

        return response()->json(
            ['message' => $createdPullRequest !== null ? 'Pull request accepted.' : 'Webhook already processed.'],
            $createdPullRequest !== null ? 202 : 200,
        );
    }

    private function reportFailure(Throwable $exception): void
    {
        report(new RuntimeException(sprintf(
            'Webhook processing failed: %s at %s:%d',
            $exception::class,
            $exception->getFile(),
            $exception->getLine(),
        )));
    }
}
