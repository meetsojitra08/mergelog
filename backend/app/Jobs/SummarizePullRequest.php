<?php

namespace App\Jobs;

use App\Enums\ChangeCategory;
use App\Enums\ChangeEntryStatus;
use App\Models\ChangeEntry;
use App\Models\PullRequest;
use App\Services\GitHubService;
use App\Services\SummarizerService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class SummarizePullRequest implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 60;

    public function __construct(public PullRequest $pullRequest) {}

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [10, 60, 300];
    }

    public function handle(SummarizerService $summarizer, GitHubService $github): void
    {
        $entry = ChangeEntry::query()
            ->where('pull_request_id', $this->pullRequest->id)
            ->firstOrFail();

        if ($entry->status === ChangeEntryStatus::Drafted) {
            return;
        }

        try {
            $files = $github->getPullRequestFiles($this->pullRequest->repository, $this->pullRequest->github_pr_number);
        } catch (Throwable) {
            $files = [];
        }

        $result = $summarizer->summarize($this->pullRequest, $files);

        $entry->fill([
            'category' => ChangeCategory::from($result['category']),
            'summary' => $result['summary'],
            'ai_raw_response' => $result['raw_response'],
            'status' => ChangeEntryStatus::Drafted,
            'error_message' => null,
        ])->save();
    }

    public function failed(Throwable $exception): void
    {
        ChangeEntry::query()
            ->where('pull_request_id', $this->pullRequest->id)
            ->where('status', ChangeEntryStatus::PendingAi)
            ->update([
                'status' => ChangeEntryStatus::Failed,
                'error_message' => $exception->getMessage(),
            ]);
    }
}
