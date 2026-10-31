<?php

namespace Tests\Feature;

use App\Enums\ChangeEntryStatus;
use App\Models\ChangeEntry;
use App\Models\PullRequest;
use App\Models\Repository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class GitHubWebhookControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_valid_signature_for_a_merged_pull_request_stores_one_pull_request_and_entry(): void
    {
        $repository = Repository::factory()->create(['webhook_secret' => 'repository-secret']);

        $this->sendWebhook($repository, $this->mergedPullRequestPayload())
            ->assertStatus(202);

        $this->assertDatabaseCount('pull_requests', 1);
        $this->assertDatabaseCount('change_entries', 1);

        $pullRequest = PullRequest::query()->sole();
        $this->assertSame($repository->id, $pullRequest->repository_id);
        $this->assertSame(42, $pullRequest->github_pr_number);
        $this->assertSame('Add webhook receiver', $pullRequest->title);
        $this->assertSame('octocat', $pullRequest->author);
        $this->assertSame('delivery-123', $pullRequest->github_delivery_id);
        $this->assertSame(ChangeEntryStatus::PendingAi, ChangeEntry::query()->sole()->status);
    }

    public function test_invalid_signature_returns_401_and_stores_nothing(): void
    {
        $repository = Repository::factory()->create(['webhook_secret' => 'repository-secret']);

        $this->sendWebhook($repository, $this->mergedPullRequestPayload(), signatureSecret: 'wrong-secret')
            ->assertUnauthorized();

        $this->assertDatabaseCount('pull_requests', 0);
        $this->assertDatabaseCount('change_entries', 0);
    }

    public function test_missing_signature_returns_401_and_stores_nothing(): void
    {
        $repository = Repository::factory()->create();

        $this->sendWebhook($repository, $this->mergedPullRequestPayload(), includeSignature: false)
            ->assertUnauthorized();

        $this->assertDatabaseCount('pull_requests', 0);
    }

    public function test_unknown_repository_returns_404(): void
    {
        $this->postJson('/api/webhooks/github/999999', [])->assertNotFound();
    }

    public function test_invalid_json_returns_400_after_signature_verification(): void
    {
        $repository = Repository::factory()->create(['webhook_secret' => 'repository-secret']);
        $rawBody = '{invalid json';

        $this->sendRawWebhook($repository, $rawBody, 'delivery-123', 'pull_request')
            ->assertStatus(400);

        $this->assertDatabaseCount('pull_requests', 0);
    }

    public function test_unsupported_event_is_acknowledged_without_storage(): void
    {
        $repository = Repository::factory()->create();

        $this->sendWebhook($repository, $this->mergedPullRequestPayload(), event: 'push')
            ->assertOk();

        $this->assertDatabaseCount('pull_requests', 0);
    }

    public function test_opened_pull_request_is_acknowledged_without_storage(): void
    {
        $repository = Repository::factory()->create();
        $payload = $this->mergedPullRequestPayload();
        $payload['action'] = 'opened';

        $this->sendWebhook($repository, $payload)->assertOk();

        $this->assertDatabaseCount('pull_requests', 0);
    }

    public function test_closed_and_merged_pull_request_is_accepted(): void
    {
        $repository = Repository::factory()->create();

        $this->sendWebhook($repository, $this->mergedPullRequestPayload())->assertStatus(202);

        $this->assertDatabaseHas('pull_requests', [
            'repository_id' => $repository->id,
            'github_pr_number' => 42,
            'merged_at' => '2026-10-31T10:15:30Z',
        ]);
        $this->assertDatabaseCount('change_entries', 1);
    }

    public function test_duplicate_delivery_is_acknowledged_without_duplicate_rows(): void
    {
        $repository = Repository::factory()->create();
        $payload = $this->mergedPullRequestPayload();

        $this->sendWebhook($repository, $payload)->assertStatus(202);
        $this->sendWebhook($repository, $payload)->assertOk();

        $this->assertDatabaseCount('pull_requests', 1);
        $this->assertDatabaseCount('change_entries', 1);
    }

    public function test_missing_required_pull_request_fields_returns_422(): void
    {
        $repository = Repository::factory()->create();
        $payload = $this->mergedPullRequestPayload();
        unset($payload['pull_request']['user']);

        $this->sendWebhook($repository, $payload)
            ->assertUnprocessable()
            ->assertJson(['message' => 'Invalid webhook payload.']);

        $this->assertDatabaseCount('pull_requests', 0);
    }

    public function test_signature_uses_the_repository_secret_decrypted_from_storage(): void
    {
        $secret = 'encrypted-webhook-secret';
        $repository = Repository::factory()->create(['webhook_secret' => $secret]);
        $storedSecret = DB::table('repositories')->where('id', $repository->id)->value('webhook_secret');

        $this->assertNotSame($secret, $storedSecret);
        $this->sendWebhook($repository, $this->mergedPullRequestPayload())->assertStatus(202);
    }

    public function test_another_repository_secret_cannot_authenticate_the_webhook(): void
    {
        $repository = Repository::factory()->create(['webhook_secret' => 'target-repository-secret']);
        $otherRepository = Repository::factory()->create(['webhook_secret' => 'other-repository-secret']);

        $this->sendWebhook(
            $repository,
            $this->mergedPullRequestPayload(),
            signatureSecret: $otherRepository->webhook_secret,
        )->assertUnauthorized();

        $this->assertDatabaseCount('pull_requests', 0);
        $this->assertDatabaseCount('change_entries', 0);
    }

    public function test_signed_ping_is_acknowledged_without_storage(): void
    {
        $repository = Repository::factory()->create();

        $this->sendWebhook($repository, ['zen' => 'Keep it logically awesome.'], event: 'ping')
            ->assertOk();

        $this->assertDatabaseCount('pull_requests', 0);
    }

    /** @param array<string, mixed> $payload */
    private function sendWebhook(
        Repository $repository,
        array $payload,
        string $event = 'pull_request',
        ?string $signatureSecret = null,
        bool $includeSignature = true,
    ) {
        $rawBody = json_encode($payload, JSON_THROW_ON_ERROR);

        return $this->sendRawWebhook(
            $repository,
            $rawBody,
            $payload['delivery_id'] ?? 'delivery-123',
            $event,
            $signatureSecret,
            $includeSignature,
        );
    }

    private function sendRawWebhook(
        Repository $repository,
        string $rawBody,
        string $deliveryId,
        string $event,
        ?string $signatureSecret = null,
        bool $includeSignature = true,
    ) {
        $headers = [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_GITHUB_EVENT' => $event,
            'HTTP_X_GITHUB_DELIVERY' => $deliveryId,
        ];

        if ($includeSignature) {
            $headers['HTTP_X_HUB_SIGNATURE_256'] = 'sha256='.hash_hmac(
                'sha256',
                $rawBody,
                $signatureSecret ?? $repository->webhook_secret,
            );
        }

        return $this->call(
            'POST',
            '/api/webhooks/github/'.$repository->id,
            [],
            [],
            [],
            $headers,
            $rawBody,
        );
    }

    /** @return array<string, mixed> */
    private function mergedPullRequestPayload(): array
    {
        return [
            'action' => 'closed',
            'number' => 42,
            'pull_request' => [
                'number' => 42,
                'title' => 'Add webhook receiver',
                'body' => 'Receive and verify GitHub webhook events.',
                'user' => ['login' => 'octocat'],
                'html_url' => 'https://github.com/octocat/hello-world/pull/42',
                'merged' => true,
                'merged_at' => '2026-10-31T10:15:30Z',
            ],
            'repository' => [
                'full_name' => 'octocat/hello-world',
                'id' => 1296269,
            ],
        ];
    }
}
