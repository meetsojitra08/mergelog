<?php

namespace Database\Factories;

use App\Enums\ChangeEntryStatus;
use App\Models\ChangeEntry;
use App\Models\PullRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChangeEntry>
 */
class ChangeEntryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array{
     *     pull_request_id: PullRequestFactory,
     *     category: null,
     *     summary: null,
     *     status: ChangeEntryStatus,
     *     ai_raw_response: null,
     *     error_message: null
     * }
     */
    public function definition(): array
    {
        return [
            'pull_request_id' => PullRequest::factory(),
            'category' => null,
            'summary' => null,
            'status' => ChangeEntryStatus::PendingAi,
            'ai_raw_response' => null,
            'error_message' => null,
        ];
    }
}
