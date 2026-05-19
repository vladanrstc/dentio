<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_from_dashboard_to_login(): void
    {
        $this->get('/dashboard')
            ->assertRedirect(route('login.show'));
    }

    public function test_platform_admin_can_access_admin_dashboard(): void
    {
        $platformAdmin = User::factory()->platformAdmin()->create();

        $this->actingAs($platformAdmin)
            ->get('/admin/dashboard')
            ->assertOk();
    }

    public function test_platform_admin_gets_forbidden_on_patients(): void
    {
        $platformAdmin = User::factory()->platformAdmin()->create();

        $this->actingAs($platformAdmin)
            ->get('/patients')
            ->assertForbidden();
    }

    public function test_company_admin_can_access_dashboard(): void
    {
        $companyAdmin = User::factory()->companyAdmin()->create();

        $this->actingAs($companyAdmin)
            ->get('/dashboard')
            ->assertOk();
    }

    public function test_company_admin_can_access_patients(): void
    {
        $companyAdmin = User::factory()->companyAdmin()->create();

        $this->actingAs($companyAdmin)
            ->get('/patients')
            ->assertOk();
    }

    public function test_dentist_can_access_patients(): void
    {
        $dentist = User::factory()->dentist()->create();

        $this->actingAs($dentist)
            ->get('/patients')
            ->assertOk();
    }

    public function test_nurse_can_access_patients(): void
    {
        $nurse = User::factory()->nurse()->create();

        $this->actingAs($nurse)
            ->get('/patients')
            ->assertOk();
    }

    public function test_dentist_cannot_access_team_invites(): void
    {
        $dentist = User::factory()->dentist()->create();

        $this->actingAs($dentist)
            ->get('/team/invites')
            ->assertForbidden();
    }

    public function test_nurse_cannot_access_team_invites(): void
    {
        $nurse = User::factory()->nurse()->create();

        $this->actingAs($nurse)
            ->get('/team/invites')
            ->assertForbidden();
    }

    public function test_company_admin_can_access_team_invites(): void
    {
        $companyAdmin = User::factory()->companyAdmin()->create();

        $this->actingAs($companyAdmin)
            ->get('/team/invites')
            ->assertOk();
    }

    public function test_company_admin_gets_forbidden_on_admin_dashboard(): void
    {
        $companyAdmin = User::factory()->companyAdmin()->create();

        $this->actingAs($companyAdmin)
            ->get('/admin/dashboard')
            ->assertForbidden();
    }
}
