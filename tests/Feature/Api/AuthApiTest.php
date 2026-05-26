<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_works_for_company_user(): void
    {
        $user = User::factory()->create([
            'company_id' => null,
            'first_name' => 'Platform',
            'last_name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => 'secret-password',
            'role' => User::ROLE_PLATFORM_ADMIN,
        ]);

        $this->postJson('/api/v1/login', [
            'email' => 'admin@example.com',
            'password' => 'secret-password',
        ])
            ->assertOk()
            ->assertJsonStructure(['token', 'user'])
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonPath('user.name', 'Platform Admin')
            ->assertJsonPath('user.email', 'admin@example.com')
            ->assertJsonPath('user.role', User::ROLE_PLATFORM_ADMIN);
    }

    public function test_login_with_invalid_credentials_returns_translated_json_error(): void
    {
        User::factory()->create([
            'email' => 'admin@example.com',
        ]);

        $this->postJson('/api/v1/login', [
            'email' => 'admin@example.com',
            'password' => 'wrong-password',
        ])
            ->assertUnprocessable()
            ->assertHeader('content-type', 'application/json')
            ->assertJsonPath('errors.email.0', __('errors.invalid_credentials'));
    }

    public function test_login_is_blocked_by_invalid_recaptcha_when_enabled(): void
    {
        config(['services.recaptcha.enabled' => true]);
        Http::fake([
            'https://www.google.com/recaptcha/api/siteverify' => Http::response([
                'success' => false,
            ]),
        ]);

        User::factory()->create([
            'email' => 'admin@example.com',
            'password' => 'secret-password',
        ]);

        $this->postJson('/api/v1/login', [
            'email' => 'admin@example.com',
            'password' => 'secret-password',
            'recaptcha_token' => 'invalid-token',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('errors.recaptcha_token.0', __('errors.recaptcha_failed'));
    }

    public function test_recaptcha_is_skipped_when_disabled(): void
    {
        config(['services.recaptcha.enabled' => false]);
        $user = User::factory()->create([
            'email' => 'admin@example.com',
            'password' => 'secret-password',
            'role' => User::ROLE_PLATFORM_ADMIN,
        ]);

        $this->postJson('/api/v1/login', [
            'email' => 'admin@example.com',
            'password' => 'secret-password',
        ])
            ->assertOk()
            ->assertJsonPath('user.id', $user->id);
    }

    public function test_logout_works_for_regular_user(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_COMPANY_ADMIN,
        ]);

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/logout')
            ->assertOk()
            ->assertJsonPath('message', __('auth.logged_out'));
    }
}
