<?php

namespace Tests\Feature\Api;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use RuntimeException;
use Tests\TestCase;

class ApiErrorResponseTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_api_request_returns_json_error(): void
    {
        $this->getJson('/api/v1/auth/me')
            ->assertStatus(401)
            ->assertJson([
                'message' => 'Unauthenticated.',
                'status' => 401,
            ]);
    }

    public function test_forbidden_api_request_returns_json_error(): void
    {
        $companyAdmin = User::factory()->companyAdmin()->create();

        $this->actingAs($companyAdmin, 'sanctum')
            ->getJson('/api/v1/admin/dashboard')
            ->assertStatus(403)
            ->assertJson([
                'message' => 'Forbidden.',
                'status' => 403,
            ]);
    }

    public function test_missing_api_endpoint_returns_json_error(): void
    {
        $this->getJson('/api/v1/not-a-real-endpoint')
            ->assertStatus(404)
            ->assertJson([
                'message' => 'Endpoint not found.',
                'status' => 404,
            ]);
    }

    public function test_missing_api_resource_returns_json_error(): void
    {
        $company = Company::factory()->create();
        $dentist = User::factory()->dentist()->forCompany($company)->create();

        $this->actingAs($dentist, 'sanctum')
            ->getJson('/api/v1/patients/999999')
            ->assertStatus(404)
            ->assertJson([
                'message' => 'Resource not found.',
                'status' => 404,
            ]);
    }

    public function test_api_method_not_allowed_returns_json_error(): void
    {
        $this->getJson('/api/v1/auth/login')
            ->assertStatus(405)
            ->assertJson([
                'message' => 'Method not allowed.',
                'status' => 405,
            ]);
    }

    public function test_api_validation_failure_returns_json_error_with_errors(): void
    {
        $this->postJson('/api/v1/auth/login', [])
            ->assertStatus(422)
            ->assertJson([
                'message' => 'The given data was invalid.',
                'status' => 422,
            ])
            ->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_generic_api_exception_returns_json_error(): void
    {
        Route::get('/api/v1/test-server-error', fn () => throw new RuntimeException('Test failure.'));

        $this->getJson('/api/v1/test-server-error')
            ->assertStatus(500)
            ->assertJson([
                'message' => 'Something went wrong.',
                'status' => 500,
            ]);
    }
}
