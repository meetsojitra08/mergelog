<?php

namespace Database\Factories;

use App\Models\PullRequest;
use App\Models\Repository;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PullRequest>
 */
class PullRequestFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array{
     *     repository_id: RepositoryFactory,
     *     github_pr_number: int,
     *     title: string,
     *     body: string,
     *     author: string,
     *     html_url: string,
     *     merged_at: CarbonInterface,
     *     github_delivery_id: string
     * }
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
