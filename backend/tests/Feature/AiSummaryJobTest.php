<?php

namespace Tests\Feature;

use App\Enums\ChangeCategory;
use App\Enums\ChangeEntryStatus;
use App\Jobs\SummarizePullRequest;
use App\Models\ChangeEntry;
use App\Models\PullRequest;
use App\Models\Repository;
use App\Models\User;
use App\Services\GitHubService;
use App\Services\SummarizerService;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use RuntimeException;
use Tests\TestCase;

class AiSummaryJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_queued_summary_timeout_covers_file_pages_and_is_shorter_than_reservations(): void
    {
        $entry = $this->pendingEntry();
        $queue = app('queue')->connection('database');
        $queue->push(new SummarizePullRequest($entry->pullRequest));

        $payload = json_decode(DB::table('jobs')->sole()->payload, true, 512, JSON_THROW_ON_ERROR);
        $this->assertGreaterThan(31 * 10 + 30, $payload['timeout']);
        foreach (['database', 'beanstalkd', 'redis'] as $connection) {
            $this->assertLessThan(config("queue.connections.{$connection}.retry_after"), $payload['timeout']);
        }
    }

    public function test_job_saves_validated_ai_summary_and_raw_provider_response(): void
    {
        $entry = $this->pendingEntry();
        $this->configureAiProvider();
        Http::fake([
            'https://api.github.com/repos/*' => Http::response([
                ['filename' => 'src/ReleaseNotes.php'],
            ]),
            'https://generativelanguage.googleapis.com/*' => Http::response($this->providerResponse([
                'category' => 'feature',
                'summary' => 'Adds release note summaries.',
            ])),
        ]);

        (new SummarizePullRequest($entry->pullRequest))->handle(app(SummarizerService::class), app(GitHubService::class));

        $entry->refresh();
        $this->assertSame(ChangeEntryStatus::Drafted, $entry->status);
        $this->assertSame(ChangeCategory::Feature, $entry->category);
        $this->assertSame('Adds release note summaries.', $entry->summary);
        $this->assertSame(
            '{"category":"feature","summary":"Adds release note summaries."}',
            data_get($entry->ai_raw_response, 'candidates.0.content.parts.0.text'),
        );
        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'generativelanguage.googleapis.com')
            && str_contains($request['contents'][0]['parts'][0]['text'], 'src/ReleaseNotes.php')
            && $request->hasHeader('x-goog-api-key', 'test-key')
        );
    }

    public function test_bad_json_throws_for_retry_and_failed_marks_the_entry(): void
    {
        $entry = $this->pendingEntry();
        $job = new SummarizePullRequest($entry->pullRequest);
        $this->configureAiProvider();
        Http::fake([
            'https://api.github.com/repos/*' => Http::response([]),
            'https://generativelanguage.googleapis.com/*' => Http::response($this->providerResponse('not valid JSON')),
        ]);

        try {
            $job->handle(app(SummarizerService::class), app(GitHubService::class));
            $this->fail('Invalid model JSON should throw so Laravel retries the job.');
        } catch (RuntimeException $exception) {
            $this->assertSame('AI provider returned invalid JSON.', $exception->getMessage());
            $job->failed($exception);
        }

        $entry->refresh();
        $this->assertSame(ChangeEntryStatus::Failed, $entry->status);
        $this->assertSame('AI provider returned invalid JSON.', $entry->error_message);
        $this->assertSame(3, $job->tries);
        $this->assertSame([10, 60, 300], $job->backoff());
    }

    public function test_provider_failure_throws_for_retry_and_failed_marks_the_entry(): void
    {
        $entry = $this->pendingEntry();
        $job = new SummarizePullRequest($entry->pullRequest);
        $this->configureAiProvider();
        Http::fake([
            'https://api.github.com/repos/*' => Http::response([]),
            'https://generativelanguage.googleapis.com/*' => Http::response(['error' => ['message' => 'unavailable']], 503),
        ]);

        try {
            $job->handle(app(SummarizerService::class), app(GitHubService::class));
            $this->fail('Provider errors should throw so Laravel retries the job.');
        } catch (RuntimeException $exception) {
            $this->assertSame('AI provider request failed with HTTP status 503.', $exception->getMessage());
            $job->failed($exception);
        }

        $entry->refresh();
        $this->assertSame(ChangeEntryStatus::Failed, $entry->status);
        $this->assertSame('AI provider request failed with HTTP status 503.', $entry->error_message);
    }

    public function test_stale_failure_callback_does_not_overwrite_a_drafted_entry(): void
    {
        $entry = $this->pendingEntry();
        $job = new SummarizePullRequest($entry->pullRequest);
        $entry->update([
            'status' => ChangeEntryStatus::Drafted,
            'summary' => 'A completed summary.',
            'error_message' => null,
        ]);

        $job->failed(new RuntimeException('Late failure'));

        $entry->refresh();
        $this->assertSame(ChangeEntryStatus::Drafted, $entry->status);
        $this->assertSame('A completed summary.', $entry->summary);
        $this->assertNull($entry->error_message);
    }

    public function test_github_file_lookup_failure_does_not_block_ai_summary(): void
    {
        $entry = $this->pendingEntry();
        $this->configureAiProvider();
        Http::fake([
            'https://api.github.com/repos/*' => Http::response(['message' => 'unavailable'], 500),
            'https://generativelanguage.googleapis.com/*' => Http::response($this->providerResponse([
                'category' => 'other',
                'summary' => 'Updates project configuration.',
            ])),
        ]);

        (new SummarizePullRequest($entry->pullRequest))->handle(app(SummarizerService::class), app(GitHubService::class));

        $entry->refresh();
        $this->assertSame(ChangeEntryStatus::Drafted, $entry->status);
        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'generativelanguage.googleapis.com')
            && str_contains($request['contents'][0]['parts'][0]['text'], 'Changed file names: (none)')
        );
    }

    public function test_github_file_lookup_collects_all_pages_and_filters_invalid_names(): void
    {
        $repository = Repository::factory()->make(['full_name' => 'octocat/hello-world', 'github_token' => 'test-token']);
        $firstPage = array_fill(0, 100, ['filename' => 'first.php']);
        $firstPage[0] = ['filename' => ''];
        Http::preventStrayRequests();
        Http::fakeSequence('https://api.github.com/repos/octocat/hello-world/pulls/42/files*')
            ->push($firstPage)
            ->push(array_fill(0, 100, ['filename' => 'second.php']))
            ->push([['filename' => 'last.php'], ['filename' => null]]);

        $files = app(GitHubService::class)->getPullRequestFiles($repository, 42);

        $this->assertCount(200, $files);
        $this->assertSame('first.php', $files[0]);
        $this->assertSame('second.php', $files[99]);
        $this->assertSame('last.php', $files[199]);
        Http::assertSentCount(3);
        foreach ([1, 2, 3] as $page) {
            Http::assertSent(function (Request $request) use ($page): bool {
                parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

                return ($query['per_page'] ?? null) === '100'
                    && ($query['page'] ?? null) === (string) $page
                    && $request->hasHeader('Authorization', 'Bearer test-token');
            });
        }
    }

    public function test_github_file_lookup_fails_when_a_later_page_fails(): void
    {
        $repository = Repository::factory()->make(['full_name' => 'octocat/hello-world']);
        Http::preventStrayRequests();
        Http::fakeSequence('https://api.github.com/repos/octocat/hello-world/pulls/42/files*')
            ->push(array_fill(0, 100, ['filename' => 'first.php']))
            ->push(['message' => 'unavailable'], 503);

        try {
            app(GitHubService::class)->getPullRequestFiles($repository, 42);
            $this->fail('A failed page should fail the file lookup.');
        } catch (RuntimeException $exception) {
            $this->assertSame('GitHub pull request files request failed.', $exception->getMessage());
        }
        Http::assertSentCount(2);
    }

    public function test_failed_entry_retry_resets_status_and_dispatches_job(): void
    {
        $user = User::factory()->create();
        $repository = Repository::factory()->for($user)->create();
        $pullRequest = PullRequest::factory()->for($repository)->create();
        $entry = ChangeEntry::factory()->for($pullRequest, 'pullRequest')->create([
            'status' => ChangeEntryStatus::Failed,
            'error_message' => 'provider unavailable',
        ]);
        Queue::fake();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/change-entries/'.$entry->id.'/retry')
            ->assertStatus(202);

        $entry->refresh();
        $this->assertSame(ChangeEntryStatus::PendingAi, $entry->status);
        $this->assertNull($entry->error_message);
        Queue::assertPushed(SummarizePullRequest::class, 1);
    }

    public function test_non_failed_entry_cannot_be_retried(): void
    {
        $user = User::factory()->create();
        $repository = Repository::factory()->for($user)->create();
        $pullRequest = PullRequest::factory()->for($repository)->create();
        $entry = ChangeEntry::factory()->for($pullRequest, 'pullRequest')->create();
        Queue::fake();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/change-entries/'.$entry->id.'/retry')
            ->assertUnprocessable();

        Queue::assertNothingPushed();
    }

    public function test_retry_does_not_dispatch_when_another_request_claims_the_entry(): void
    {
        $user = User::factory()->create();
        $repository = Repository::factory()->for($user)->create();
        $pullRequest = PullRequest::factory()->for($repository)->create();
        $entry = ChangeEntry::factory()->for($pullRequest, 'pullRequest')->create(['status' => ChangeEntryStatus::Failed]);
        Queue::fake();
        $claimed = false;
        DB::listen(function (QueryExecuted $query) use ($entry, &$claimed): void {
            if (! $claimed && str_starts_with(strtolower($query->sql), 'select') && str_contains($query->sql, 'change_entries')) {
                $claimed = true;
                ChangeEntry::query()->whereKey($entry->id)->update(['status' => ChangeEntryStatus::PendingAi]);
            }
        });

        $this->actingAs($user, 'sanctum')->postJson('/api/change-entries/'.$entry->id.'/retry')->assertUnprocessable();

        $this->assertTrue($claimed);
        $this->assertSame(ChangeEntryStatus::PendingAi, $entry->fresh()->status);
        Queue::assertNothingPushed();
    }

    public function test_retry_dispatch_failure_restores_failed_state_and_allows_a_later_retry(): void
    {
        $user = User::factory()->create();
        $repository = Repository::factory()->for($user)->create();
        $pullRequest = PullRequest::factory()->for($repository)->create();
        $entry = ChangeEntry::factory()->for($pullRequest, 'pullRequest')->create(['status' => ChangeEntryStatus::Failed]);
        Bus::shouldReceive('dispatch')->once()->andThrow(new RuntimeException('queue unavailable'));
        Exceptions::fake();

        $this->actingAs($user, 'sanctum')->postJson('/api/change-entries/'.$entry->id.'/retry')->assertStatus(500);

        $this->assertSame(ChangeEntryStatus::Failed, $entry->fresh()->status);
        $this->assertSame('queue unavailable', $entry->fresh()->error_message);
        Exceptions::assertReported(RuntimeException::class);

        Bus::fake();
        $this->actingAs($user, 'sanctum')->postJson('/api/change-entries/'.$entry->id.'/retry')->assertStatus(202);
        $this->assertSame(ChangeEntryStatus::PendingAi, $entry->fresh()->status);
        $this->assertNull($entry->fresh()->error_message);
        Bus::assertDispatched(SummarizePullRequest::class, 1);
    }

    public function test_user_cannot_retry_an_entry_from_another_users_repository(): void
    {
        $repository = Repository::factory()->create();
        $pullRequest = PullRequest::factory()->for($repository)->create();
        $entry = ChangeEntry::factory()->for($pullRequest, 'pullRequest')->create([
            'status' => ChangeEntryStatus::Failed,
        ]);
        Queue::fake();

        $this->actingAs(User::factory()->create(), 'sanctum')
            ->postJson('/api/change-entries/'.$entry->id.'/retry')
            ->assertNotFound();

        Queue::assertNothingPushed();
    }

    private function pendingEntry(): ChangeEntry
    {
        $repository = Repository::factory()->create(['full_name' => 'octocat/hello-world']);
        $pullRequest = PullRequest::factory()->for($repository)->create([
            'github_pr_number' => 42,
            'title' => 'Add changelog summaries',
            'body' => 'Summarize merged changes.',
        ]);

        return ChangeEntry::factory()->for($pullRequest, 'pullRequest')->create();
    }

    private function configureAiProvider(): void
    {
        config([
            'services.ai.api_url' => 'https://generativelanguage.googleapis.com/v1beta/models/{model}:generateContent',
            'services.ai.model' => 'gemini-test',
            'services.ai.api_key' => 'test-key',
        ]);
    }

    /**
     * @param  array{category: string, summary: string}|string  $text
     * @return array<string, mixed>
     */
    private function providerResponse(array|string $text): array
    {
        return [
            'candidates' => [[
                'content' => [
                    'parts' => [[
                        'text' => is_array($text) ? json_encode($text, JSON_THROW_ON_ERROR) : $text,
                    ]],
                ],
            ]],
        ];
    }
}
