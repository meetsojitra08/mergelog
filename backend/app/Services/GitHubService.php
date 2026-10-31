<?php

namespace App\Services;

use App\Models\Repository;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class GitHubService
{
    /**
     * @return array<int, string>
     */
    public function getPullRequestFiles(Repository $repo, int $number): array
    {
        $request = Http::acceptJson()
            ->withHeaders([
                'Accept' => 'application/vnd.github+json',
                'X-GitHub-Api-Version' => '2022-11-28',
            ])
            ->timeout(10);

        if (is_string($repo->github_token) && $repo->github_token !== '') {
            $request = $request->withToken($repo->github_token);
        }

        try {
            $response = $request->get(sprintf(
                'https://api.github.com/repos/%s/pulls/%d/files',
                $repo->full_name,
                $number,
            ));
        } catch (\Throwable $exception) {
            throw new RuntimeException('GitHub pull request files request failed.', previous: $exception);
        }

        if (! $response->successful() || ! is_array($response->json())) {
            throw new RuntimeException('GitHub pull request files request failed.');
        }

        return collect($response->json())
            ->pluck('filename')
            ->filter(fn (mixed $filename): bool => is_string($filename) && $filename !== '')
            ->values()
            ->all();
    }
}
