<?php

namespace Tests\Feature;

use App\Enums\ChangeEntryStatus;
use App\Models\ChangeEntry;
use App\Models\PullRequest;
use Database\Seeders\ChangeEntrySeeder;
use Database\Seeders\PullRequestSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModelFactorySeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_factories_create_related_records_with_pending_change_entries(): void
    {
        $entry = ChangeEntry::factory()->create();

        $this->assertSame(ChangeEntryStatus::PendingAi, $entry->status);
        $this->assertNotNull($entry->pullRequest->repository);
        $this->assertStringEndsWith('/pull/'.$entry->pullRequest->github_pr_number, $entry->pullRequest->html_url);
    }

    public function test_seeders_create_valid_records(): void
    {
        $this->seed(PullRequestSeeder::class);
        $this->seed(ChangeEntrySeeder::class);

        $this->assertDatabaseCount('pull_requests', 10);
        $this->assertDatabaseCount('change_entries', 5);
        $this->assertSame(5, ChangeEntry::query()->where('status', ChangeEntryStatus::PendingAi->value)->count());
        $this->assertSame(10, PullRequest::query()->count());
    }
}
