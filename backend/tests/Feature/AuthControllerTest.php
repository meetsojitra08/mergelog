<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_creates_a_user_and_returns_a_token(): void
    {
        $password = fake()->password(12);
        $email = fake()->unique()->safeEmail();

        $response = $this->postJson('/api/register', [
            'name' => 'Mergelog User',
            'email' => $email,
            'password' => $password,
            'password_confirmation' => $password,
        ]);

        $response->assertCreated()
            ->assertJsonStructure([
                'token',
                'user' => ['id', 'name', 'email'],
            ])
            ->assertJsonPath('user.email', $email)
            ->assertJsonMissingPath('user.password');

        $this->assertDatabaseHas('users', ['email' => $email]);
        $this->assertDatabaseCount('personal_access_tokens', 1);

        $user = User::query()->where('email', $email)->firstOrFail();
        $this->assertTrue(Hash::check($password, $user->password));
    }

    public function test_register_returns_validation_error_for_duplicate_email(): void
    {
        $user = User::factory()->create();
        $password = fake()->password(12);

        $response = $this->postJson('/api/register', [
            'name' => 'Another User',
            'email' => $user->email,
            'password' => $password,
            'password_confirmation' => $password,
        ]);

        $response->assertUnprocessable()
            ->assertInvalid(['email']);

        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_login_returns_a_token_for_valid_credentials(): void
    {
        $password = fake()->password(12);
        $user = User::factory()->create(['password' => $password]);

        $response = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => $password,
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'token',
                'user' => ['id', 'name', 'email'],
            ])
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonMissingPath('user.password');

        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    public function test_login_returns_422_for_an_incorrect_password(): void
    {
        $password = fake()->password(12);
        $user = User::factory()->create(['password' => $password]);

        $response = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => $password.'-incorrect',
        ]);

        $response->assertUnprocessable()
            ->assertInvalid(['email' => 'The provided credentials are incorrect.']);

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_login_is_limited_to_five_attempts_per_minute(): void
    {
        $email = fake()->unique()->safeEmail();

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson('/api/login', [
                'email' => $email,
                'password' => fake()->password(12),
            ])->assertUnprocessable();
        }

        $this->postJson('/api/login', [
            'email' => $email,
            'password' => fake()->password(12),
        ])->assertTooManyRequests();
    }

    public function test_logout_revokes_the_current_token_and_returns_no_content(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('mergelog')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/logout')
            ->assertNoContent();

        $this->assertDatabaseCount('personal_access_tokens', 0);

        Auth::forgetGuards();

        $this->withToken($token)
            ->getJson('/api/me')
            ->assertUnauthorized();
    }

    public function test_me_returns_the_authenticated_user(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('mergelog')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/me')
            ->assertOk()
            ->assertExactJson([
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ]);
    }

    public function test_me_returns_401_without_a_token(): void
    {
        $this->getJson('/api/me')->assertUnauthorized();
    }

    public function test_cors_only_allows_the_configured_frontend_origin(): void
    {
        $allowedResponse = $this->withHeaders([
            'Origin' => 'http://localhost:5173',
            'Access-Control-Request-Method' => 'GET',
            'Access-Control-Request-Headers' => 'authorization,content-type',
        ])->options('/api/me');

        $allowedResponse->assertNoContent()
            ->assertHeader('Access-Control-Allow-Origin', 'http://localhost:5173');

        $blockedResponse = $this->withHeaders([
            'Origin' => 'https://untrusted.example',
            'Access-Control-Request-Method' => 'GET',
            'Access-Control-Request-Headers' => 'authorization,content-type',
        ])->options('/api/me');

        $blockedResponse->assertNoContent()
            ->assertHeader('Access-Control-Allow-Origin', 'http://localhost:5173');
    }
}
