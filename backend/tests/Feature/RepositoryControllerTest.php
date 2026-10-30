<?php

namespace Tests\Feature;

use App\Models\Repository;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RepositoryControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_a_repository_with_a_generated_webhook_secret(): void
    {
        $owner = User::factory()->create();
        $anotherUser = User::factory()->create();
        $githubToken = 'ghp-example-private-token';

        $response = $this->actingAs($owner, 'sanctum')->postJson('/api/repositories', [
            'full_name' => 'octocat/hello-world',
            'github_token' => $githubToken,
            'user_id' => $anotherUser->id,
        ]);

        $response->assertCreated()
            ->assertJsonStructure(['id', 'full_name', 'webhook_url', 'webhook_secret'])
            ->assertJsonPath('full_name', 'octocat/hello-world')
            ->assertJsonPath('webhook_url', config('app.url').'/api/webhooks/github/'.$response->json('id'))
            ->assertJsonMissingPath('github_token')
            ->assertJsonMissingPath('user_id');

        $response->assertDontSee($githubToken);

        $repository = Repository::query()->findOrFail($response->json('id'));
        $this->assertSame($owner->id, $repository->user_id);
        $this->assertSame(40, strlen($response->json('webhook_secret')));
        $this->assertSame('octocat/hello-world', $repository->full_name);
    }

    public function test_user_can_list_only_their_repositories_without_secrets_or_tokens(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $repository = Repository::factory()->for($owner)->create([
            'full_name' => 'octocat/hello-world',
            'webhook_secret' => 'list-secret',
            'github_token' => 'ghp-list-private-token',
        ]);
        Repository::factory()->for($otherUser)->create(['full_name' => 'other/private']);

        $this->actingAs($owner, 'sanctum')
            ->getJson('/api/repositories')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonStructure([['id', 'full_name', 'pending_count', 'drafted_count', 'created_at']])
            ->assertJsonPath('0.id', $repository->id)
            ->assertJsonPath('0.pending_count', 0)
            ->assertJsonPath('0.drafted_count', 0)
            ->assertJsonMissingPath('0.webhook_secret')
            ->assertJsonMissingPath('0.github_token')
            ->assertDontSee('list-secret')
            ->assertDontSee('ghp-list-private-token')
            ->assertDontSee('other/private');
    }

    public function test_user_can_view_their_repository_and_only_the_webhook_secret_is_exposed(): void
    {
        $owner = User::factory()->create();
        $repository = Repository::factory()->for($owner)->create([
            'full_name' => 'octocat/hello-world',
            'webhook_secret' => 'show-secret',
            'github_token' => 'ghp-show-private-token',
        ]);

        $this->actingAs($owner, 'sanctum')
            ->getJson('/api/repositories/'.$repository->id)
            ->assertOk()
            ->assertExactJson([
                'id' => $repository->id,
                'full_name' => 'octocat/hello-world',
                'webhook_url' => config('app.url').'/api/webhooks/github/'.$repository->id,
                'webhook_secret' => 'show-secret',
            ])
            ->assertDontSee('ghp-show-private-token');
    }

    public function test_user_can_delete_their_repository(): void
    {
        $owner = User::factory()->create();
        $repository = Repository::factory()->for($owner)->create();

        $this->actingAs($owner, 'sanctum')
            ->deleteJson('/api/repositories/'.$repository->id)
            ->assertNoContent();

        $this->assertDatabaseMissing('repositories', ['id' => $repository->id]);
    }

    public function test_another_users_repository_cannot_be_viewed_or_deleted(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $repository = Repository::factory()->for($owner)->create();

        $this->actingAs($otherUser, 'sanctum')
            ->getJson('/api/repositories/'.$repository->id)
            ->assertNotFound();

        $this->actingAs($otherUser, 'sanctum')
            ->deleteJson('/api/repositories/'.$repository->id)
            ->assertNotFound();

        $this->assertDatabaseHas('repositories', ['id' => $repository->id, 'user_id' => $owner->id]);
    }

    public function test_repository_name_must_be_unique_per_user(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        Repository::factory()->for($owner)->create(['full_name' => 'octocat/hello-world']);

        $this->actingAs($owner, 'sanctum')
            ->postJson('/api/repositories', ['full_name' => 'octocat/hello-world'])
            ->assertUnprocessable()
            ->assertInvalid(['full_name']);

        $this->actingAs($otherUser, 'sanctum')
            ->postJson('/api/repositories', ['full_name' => 'octocat/hello-world'])
            ->assertCreated();
    }

    public function test_invalid_repository_names_return_validation_errors(): void
    {
        $owner = User::factory()->create();

        foreach (['owner', '/repo', 'owner/', 'owner/repo/extra', 'owner/repo name'] as $fullName) {
            $this->actingAs($owner, 'sanctum')
                ->postJson('/api/repositories', ['full_name' => $fullName])
                ->assertUnprocessable()
                ->assertInvalid(['full_name']);
        }
    }

    public function test_webhook_secret_and_github_token_are_encrypted_in_the_database(): void
    {
        $owner = User::factory()->create();
        $secret = 'plain-webhook-secret';
        $token = 'ghp-plain-private-token';
        $repository = Repository::factory()->for($owner)->create([
            'webhook_secret' => $secret,
            'github_token' => $token,
        ]);

        $storedValues = DB::table('repositories')->where('id', $repository->id)->first();

        $this->assertNotSame($secret, $storedValues->webhook_secret);
        $this->assertNotSame($token, $storedValues->github_token);
        $this->assertSame($secret, Crypt::decryptString($storedValues->webhook_secret));
        $this->assertSame($token, Crypt::decryptString($storedValues->github_token));
        $this->assertSame($secret, $repository->fresh()->webhook_secret);
        $this->assertSame($token, $repository->fresh()->github_token);
    }

    public function test_repository_endpoints_require_authentication(): void
    {
        $this->getJson('/api/repositories')->assertUnauthorized();
        $this->postJson('/api/repositories', ['full_name' => 'octocat/hello-world'])->assertUnauthorized();
    }

    public function test_non_numeric_repository_ids_return_404_before_authentication(): void
    {
        $this->getJson('/api/repositories/not-a-number')->assertNotFound();
        $this->deleteJson('/api/repositories/not-a-number')->assertNotFound();
    }
}
