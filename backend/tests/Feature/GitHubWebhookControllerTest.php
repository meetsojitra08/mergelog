<?php

namespace Tests\Feature;

use App\Enums\ChangeEntryStatus;
use App\Models\ChangeEntry;
use App\Models\PullRequest;
use App\Models\Repository;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
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

    public function test_invalid_repository_ids_are_rejected_before_a_repository_query(): void
    {
        $queries = [];
        DB::listen(function (QueryExecuted $query) use (&$queries): void {
            $queries[] = $query->sql;
        });

        foreach (['abc', '-1', '9223372036854775808', '1000000000000000000000000'] as $id) {
            $this->postJson('/api/webhooks/github/'.$id, [])->assertNotFound();
        }

        $this->assertSame([], $queries);
    }

    public function test_maximum_repository_id_is_queried_and_leading_zeroes_preserve_valid_ids(): void
    {
        $repository = Repository::factory()->create();
        $queries = [];
        DB::listen(function (QueryExecuted $query) use (&$queries): void {
            $queries[] = $query->bindings;
        });

        $this->postJson('/api/webhooks/github/9223372036854775807', [])->assertNotFound();
        $this->assertContains(['9223372036854775807'], $queries);

        $this->postJson('/api/webhooks/github/000'.$repository->id, [])->assertUnauthorized();
    }

    public function test_decryption_failure_is_reported_without_the_secret_or_payload(): void
    {
        $repository = Repository::factory()->create();
        DB::table('repositories')->where('id', $repository->id)->update(['webhook_secret' => 'invalid-ciphertext']);
        Exceptions::fake();

        $this->postJson('/api/webhooks/github/'.$repository->id, ['sensitive' => 'payload-marker'])
            ->assertStatus(500)
            ->assertJson(['message' => 'Unable to process webhook.']);

        Exceptions::assertReported(fn (RuntimeException $exception): bool => str_contains($exception->getMessage(), 'DecryptException')
            && ! str_contains($exception->getMessage(), 'invalid-ciphertext')
            && ! str_contains($exception->getMessage(), 'payload-marker')
            && $exception->getPrevious() === null
        );
    }

    public function test_database_failure_is_reported_without_payload_and_rolls_back(): void
    {
        $repository = Repository::factory()->create();
        Schema::drop('change_entries');
        Exceptions::fake();
        $payload = $this->mergedPullRequestPayload();
        $payload['pull_request']['body'] = 'payload-marker';

        $this->sendWebhook($repository, $payload)->assertStatus(500)
            ->assertJson(['message' => 'Unable to process webhook.']);

        Exceptions::assertReported(fn (RuntimeException $exception): bool => str_contains($exception->getMessage(), 'QueryException')
            && ! str_contains($exception->getMessage(), 'payload-marker')
            && ! str_contains($exception->getMessage(), $repository->webhook_secret)
            && $exception->getPrevious() === null
        );
        $this->assertDatabaseCount('pull_requests', 0);
    }

    public function test_long_url_is_stored_up_to_the_validation_limit(): void
    {
        $repository = Repository::factory()->create();
        $payload = $this->mergedPullRequestPayload();
        $prefix = 'https://example.com/';
        $payload['pull_request']['html_url'] = $prefix.str_repeat('a', 2048 - strlen($prefix));

        $this->sendWebhook($repository, $payload)->assertStatus(202);
        $this->assertSame($payload['pull_request']['html_url'], PullRequest::query()->sole()->html_url);
        if (DB::getDriverName() === 'pgsql') {
            $column = collect(Schema::getColumns('pull_requests'))->firstWhere('name', 'html_url');
            $this->assertSame('character varying(2048)', $column['type']);
        }
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
