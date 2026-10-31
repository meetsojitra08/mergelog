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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use RuntimeException;
use Tests\TestCase;

class AiSummaryJobTest extends TestCase
{
    use RefreshDatabase;

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
