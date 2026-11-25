<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

/**
 * Feature tests for /api/auth/*.
 *
 * WHY: these pin the token envelope, the coded error contract, and the two
 * security invariants that matter most — passwords are hashed and a logout
 * only revokes the token that made the request.
 */
class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_creates_user_hashes_password_and_returns_token(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Ada Lovelace',
            // Mixed case proves the address is normalised before storage.
            'email' => 'Ada@Example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.user.name', 'Ada Lovelace')
            ->assertJsonPath('data.user.email', 'ada@example.com')
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonStructure([
                'data' => [
                    'user' => ['id', 'name', 'email', 'email_verified_at', 'created_at', 'updated_at'],
                    'token',
                    'token_type',
                ],
            ]);

        $this->assertNotEmpty($response->json('data.token'));
        $this->assertArrayNotHasKey('password', $response->json('data.user'));

        $user = User::query()->where('email', 'ada@example.com')->firstOrFail();
        $this->assertNotSame('password123', $user->password);
        $this->assertTrue(Hash::check('password123', $user->password));

        // Sanctum persists only a hash of the token, plus its owner.
        $token = PersonalAccessToken::query()->firstOrFail();
        $this->assertSame('api', $token->name);
        $this->assertSame($user->id, $token->tokenable_id);
    }

    public function test_register_rejects_duplicate_email_with_validation_error(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $this->postJson('/api/auth/register', [
            'name' => 'Another User',
            'email' => 'TAKEN@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'VALIDATION_ERROR')
            ->assertJsonPath('message', 'The given parameters were invalid.')
            ->assertJsonStructure(['message', 'code', 'errors' => ['email']]);

        $this->assertSame(1, User::count());
        $this->assertSame(0, PersonalAccessToken::count());
    }

    public function test_register_validates_all_fields(): void
    {
        $this->postJson('/api/auth/register', [
            'name' => '',
            'email' => 'not-an-email',
            'password' => 'short',
            'password_confirmation' => 'different',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'VALIDATION_ERROR')
            ->assertJsonStructure(['errors' => ['name', 'email', 'password']]);

        $this->assertSame(0, User::count());
    }

    public function test_array_email_returns_coded_validation_error(): void
    {
        $this->postJson('/api/auth/register', [
            'name' => 'Ada Lovelace',
            'email' => ['nested' => 'not-an-email'],
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'VALIDATION_ERROR')
            ->assertJsonStructure(['message', 'code', 'errors' => ['email']]);

        $this->postJson('/api/auth/login', [
            'email' => ['nested' => 'not-an-email'],
            'password' => 'password123',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'VALIDATION_ERROR')
            ->assertJsonStructure(['message', 'code', 'errors' => ['email']]);

        $this->assertSame(0, User::count());
        $this->assertSame(0, PersonalAccessToken::count());
    }

    public function test_login_returns_token_for_valid_credentials(): void
    {
        $user = User::factory()->create([
            'email' => 'login@example.com',
            'password' => Hash::make('password123'),
        ]);

        $response = $this->postJson('/api/auth/login', [
            // Login normalises the address the same way registration does.
            'email' => 'LOGIN@example.com',
            'password' => 'password123',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonPath('data.user.email', 'login@example.com')
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonStructure(['data' => ['user', 'token', 'token_type']]);

        $this->assertNotEmpty($response->json('data.token'));
        $this->assertArrayNotHasKey('password', $response->json('data.user'));
        $this->assertSame(1, PersonalAccessToken::count());
    }

    public function test_login_rejects_wrong_password_and_unknown_email(): void
    {
        User::factory()->create(['email' => 'user@example.com']);

        foreach ([
            ['email' => 'user@example.com', 'password' => 'wrong-password'],
            ['email' => 'missing@example.com', 'password' => 'password123'],
        ] as $credentials) {
            $this->postJson('/api/auth/login', $credentials)
                ->assertStatus(401)
                ->assertJsonPath('code', 'INVALID_CREDENTIALS')
                ->assertJsonPath('message', 'The provided credentials are incorrect.')
                ->assertJsonMissingValidationErrors();
        }

        // Failed logins must never issue a token.
        $this->assertSame(0, PersonalAccessToken::count());
    }

    public function test_login_validates_payload(): void
    {
        $this->postJson('/api/auth/login', [])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'VALIDATION_ERROR')
            ->assertJsonStructure(['errors' => ['email', 'password']]);

        $this->postJson('/api/auth/login', ['email' => 'bad', 'password' => 'x'])
            ->assertUnprocessable()
            ->assertJsonStructure(['errors' => ['email']]);
    }

    public function test_logout_deletes_only_the_current_token(): void
    {
        $user = User::factory()->create();
        $currentToken = $user->createToken('api')->plainTextToken;
        $otherToken = $user->createToken('other-device')->plainTextToken;

        $this->withToken($currentToken)
            ->postJson('/api/auth/logout')
            ->assertOk()
            ->assertJsonPath('data.message', 'Logged out successfully.');

        $this->assertSame(1, PersonalAccessToken::count());
        $this->assertSame('other-device', PersonalAccessToken::query()->firstOrFail()->name);

        // WHY: Laravel's RequestGuard caches the resolved user for the life of
        // the app instance, and feature tests reuse that instance across
        // requests. Real HTTP requests get a fresh app, so clear the guards
        // here to make the next call behave like a genuinely new request.
        Auth::forgetGuards();

        // The revoked token stops working; the other device stays signed in.
        $this->withToken($currentToken)->getJson('/api/auth/me')->assertStatus(401);
        $this->withToken($otherToken)->getJson('/api/auth/me')->assertOk();
    }

    public function test_logout_requires_authentication(): void
    {
        $this->postJson('/api/auth/logout')
            ->assertStatus(401)
            ->assertJsonPath('code', 'UNAUTHENTICATED')
            ->assertJsonPath('message', 'Authentication is required.');
    }

    public function test_me_returns_authenticated_user_without_sensitive_fields(): void
    {
        $user = User::factory()->create(['name' => 'Token Owner']);
        $token = $user->createToken('api')->plainTextToken;

        $response = $this->withToken($token)->getJson('/api/auth/me');

        $response->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.name', 'Token Owner')
            ->assertJsonPath('data.email', $user->email)
            ->assertJsonStructure([
                'data' => ['id', 'name', 'email', 'email_verified_at', 'created_at', 'updated_at'],
            ]);

        $this->assertArrayNotHasKey('password', $response->json('data'));
        $this->assertArrayNotHasKey('remember_token', $response->json('data'));
        $this->assertArrayNotHasKey('token', $response->json('data'));
    }

    public function test_me_requires_authentication(): void
    {
        $this->getJson('/api/auth/me')
            ->assertStatus(401)
            ->assertJsonPath('code', 'UNAUTHENTICATED')
            ->assertJsonPath('message', 'Authentication is required.');
    }
}
