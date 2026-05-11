<?php

namespace Tests\Feature;

use App\Models\User;
// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_the_application_redirects_guest_to_login(): void
    {
        config(['session.driver' => 'array']);

        $response = $this->get('/');

        $response->assertRedirect(route('login.show'));
    }

    public function test_platform_admin_is_redirected_to_admin_dashboard_from_home(): void
    {
        config(['session.driver' => 'array']);

        $platformAdmin = new User();
        $platformAdmin->forceFill([
            'id' => 101,
            'name' => 'Platform Admin',
            'email' => 'platform-admin@example.com',
            'password' => 'irrelevant',
            'role' => User::ROLE_PLATFORM_ADMIN,
            'company_id' => null,
        ]);

        $this->actingAs($platformAdmin);

        $response = $this->get('/');

        $response->assertRedirect(route('admin.dashboard'));
    }

    public function test_company_user_is_redirected_to_company_dashboard_from_home(): void
    {
        config(['session.driver' => 'array']);

        $dentist = new User();
        $dentist->forceFill([
            'id' => 202,
            'name' => 'Company Dentist',
            'email' => 'dentist@example.com',
            'password' => 'irrelevant',
            'role' => User::ROLE_DENTIST,
            'company_id' => 1,
        ]);

        $this->actingAs($dentist);

        $response = $this->get('/');

        $response->assertRedirect(route('dashboard.index'));
    }
}
