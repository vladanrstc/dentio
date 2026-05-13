<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use RefreshDatabase;

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
}
