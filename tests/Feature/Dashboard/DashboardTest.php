<?php

namespace Tests\Feature\Dashboard;

use App\Models\Appointment;
use App\Models\Company;
use App\Models\Intervention;
use App\Models\Patient;
use App\Models\PatientTask;
use App\Models\Reminder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_admin_can_view_company_dashboard(): void
    {
        $companyAdmin = User::factory()->companyAdmin()->create();

        $this->actingAs($companyAdmin)
            ->get(route('dashboard.index'))
            ->assertOk()
            ->assertSee('Pacijenti')
            ->assertSee('Danasnji termini');
    }

    public function test_company_dashboard_shows_only_data_from_authenticated_users_company(): void
    {
        $ownCompany = Company::factory()->create();
        $otherCompany = Company::factory()->create();
        $companyAdmin = User::factory()->companyAdmin()->forCompany($ownCompany)->create();
        $ownPatient = Patient::factory()->create(['company_id' => $ownCompany->id]);
        $otherPatient = Patient::factory()->create(['company_id' => $otherCompany->id]);

        PatientTask::factory()->create([
            'company_id' => $ownCompany->id,
            'patient_id' => $ownPatient->id,
            'status' => PatientTask::STATUS_OPEN,
        ]);
        PatientTask::factory()->create([
            'company_id' => $otherCompany->id,
            'patient_id' => $otherPatient->id,
            'status' => PatientTask::STATUS_OPEN,
        ]);
        Appointment::factory()->create([
            'company_id' => $ownCompany->id,
            'patient_id' => $ownPatient->id,
            'starts_at' => now()->setTime(10, 0),
            'status' => Appointment::STATUS_SCHEDULED,
        ]);
        Appointment::factory()->create([
            'company_id' => $otherCompany->id,
            'patient_id' => $otherPatient->id,
            'starts_at' => now()->setTime(11, 0),
            'status' => Appointment::STATUS_SCHEDULED,
        ]);
        Reminder::factory()->create([
            'company_id' => $ownCompany->id,
            'patient_id' => $ownPatient->id,
            'status' => Reminder::STATUS_PENDING,
            'remind_at' => now()->subMinute(),
        ]);
        Reminder::factory()->create([
            'company_id' => $otherCompany->id,
            'patient_id' => $otherPatient->id,
            'status' => Reminder::STATUS_PENDING,
            'remind_at' => now()->subMinute(),
        ]);
        Intervention::factory()->create([
            'company_id' => $ownCompany->id,
            'patient_id' => $ownPatient->id,
            'total_cost' => 5000,
            'paid_amount' => 2000,
        ]);
        Intervention::factory()->create([
            'company_id' => $otherCompany->id,
            'patient_id' => $otherPatient->id,
            'total_cost' => 9000,
            'paid_amount' => 1000,
        ]);

        $this->actingAs($companyAdmin)
            ->get(route('dashboard.index'))
            ->assertOk()
            ->assertSee('>1</div>', false)
            ->assertSee('3,000.00 RSD')
            ->assertDontSee('8,000.00 RSD');
    }

    public function test_platform_admin_can_view_platform_dashboard(): void
    {
        $platformAdmin = User::factory()->platformAdmin()->create();

        $this->actingAs($platformAdmin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Platform Admin')
            ->assertSee('Kompanije na platformi');
    }

    public function test_platform_dashboard_shows_companies(): void
    {
        $platformAdmin = User::factory()->platformAdmin()->create();
        $company = Company::factory()->create([
            'name' => 'Dental Test Company',
            'address' => 'Test Address 10',
        ]);

        $this->actingAs($platformAdmin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Dental Test Company')
            ->assertSee('Test Address 10');
    }

    public function test_company_user_cannot_access_platform_dashboard(): void
    {
        $companyAdmin = User::factory()->companyAdmin()->create();

        $this->actingAs($companyAdmin)
            ->get(route('admin.dashboard'))
            ->assertForbidden();
    }

    public function test_platform_admin_cannot_access_company_dashboard(): void
    {
        $platformAdmin = User::factory()->platformAdmin()->create();

        $this->actingAs($platformAdmin)
            ->get(route('dashboard.index'))
            ->assertForbidden();
    }
}
