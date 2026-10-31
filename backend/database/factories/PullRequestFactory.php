<?php

namespace Database\Factories;

use App\Models\PullRequest;
use App\Models\Repository;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PullRequest>
 */
class PullRequestFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $number = fake()->numberBetween(1, 1000000000);

        return [
            'repository_id' => Repository::factory(),
            'github_pr_number' => $number,
            'title' => fake()->sentence(),
            'body' => fake()->paragraph(),
            'author' => fake()->userName(),
            'html_url' => 'https://github.com/example/repository/pull/'.$number,
            'merged_at' => now(),
            'github_delivery_id' => fake()->uuid(),
        ];
    }
}
