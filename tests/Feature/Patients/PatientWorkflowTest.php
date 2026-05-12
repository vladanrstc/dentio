<?php

namespace Tests\Feature\Patients;

use App\Models\Appointment;
use App\Models\Company;
use App\Models\Intervention;
use App\Models\Patient;
use App\Models\PatientTask;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PatientWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private const CSRF_TOKEN = 'test-token';

    public function test_company_admin_can_create_a_patient(): void
    {
        $context = $this->createCompanyContext();

        $this->actingAs($context['companyAdmin'])
            ->withSession(['_token' => self::CSRF_TOKEN])
            ->post(route('patients.store'), $this->formData([
                'first_name' => 'Mila',
                'last_name' => 'Petrovic',
                'address' => 'Test adresa 1',
                'phone' => '060123456',
                'email' => 'mila@example.com',
                'primary_dentist_id' => $context['dentist']->id,
                'notes' => 'Prvi pregled.',
            ]))
            ->assertRedirect();

        $this->assertDatabaseHas('patients', [
            'company_id' => $context['company']->id,
            'first_name' => 'Mila',
            'last_name' => 'Petrovic',
            'phone' => '060123456',
            'email' => 'mila@example.com',
            'primary_dentist_id' => $context['dentist']->id,
        ]);
    }

    public function test_company_admin_can_update_patient_basic_information(): void
    {
        $context = $this->createCompanyContext();

        $this->actingAs($context['companyAdmin'])
            ->withSession(['_token' => self::CSRF_TOKEN])
            ->put(route('patients.update', $context['patient']->id), $this->formData([
                'first_name' => 'Updated',
                'last_name' => 'Patient',
                'address' => 'Updated address',
                'phone' => '061999888',
                'email' => 'updated@example.com',
                'primary_dentist_id' => $context['dentist']->id,
                'notes' => 'Updated notes.',
            ]))
            ->assertRedirect();

        $this->assertDatabaseHas('patients', [
            'id' => $context['patient']->id,
            'first_name' => 'Updated',
            'last_name' => 'Patient',
            'address' => 'Updated address',
            'phone' => '061999888',
            'email' => 'updated@example.com',
        ]);
    }

    public function test_company_admin_can_change_patient_status_and_status_log_is_created(): void
    {
        $context = $this->createCompanyContext();

        $this->actingAs($context['companyAdmin'])
            ->withSession(['_token' => self::CSRF_TOKEN])
            ->patch(route('patients.status.update', $context['patient']->id), $this->formData([
                'manual_status' => Patient::STATUS_INACTIVE,
                'manual_status_reason' => 'Pacijent pauzira terapiju.',
            ]))
            ->assertRedirect();

        $this->assertDatabaseHas('patients', [
            'id' => $context['patient']->id,
            'manual_status' => Patient::STATUS_INACTIVE,
            'manual_status_reason' => 'Pacijent pauzira terapiju.',
            'manual_status_changed_by_user_id' => $context['companyAdmin']->id,
        ]);

        $this->assertDatabaseHas('patient_status_logs', [
            'company_id' => $context['company']->id,
            'patient_id' => $context['patient']->id,
            'changed_by_user_id' => $context['companyAdmin']->id,
            'previous_status' => Patient::STATUS_ACTIVE,
            'new_status' => Patient::STATUS_INACTIVE,
            'reason' => 'Pacijent pauzira terapiju.',
        ]);
    }

    public function test_company_admin_can_create_a_patient_task(): void
    {
        $context = $this->createCompanyContext();

        $this->actingAs($context['companyAdmin'])
            ->withSession(['_token' => self::CSRF_TOKEN])
            ->post(route('patients.tasks.store', $context['patient']->id), $this->formData([
                'description' => 'Pozvati pacijenta za kontrolu.',
                'due_date' => '2026-06-01',
                'assigned_to_user_id' => $context['dentist']->id,
            ]))
            ->assertRedirect();

        $this->assertDatabaseHas('patient_tasks', [
            'company_id' => $context['company']->id,
            'patient_id' => $context['patient']->id,
            'created_by_user_id' => $context['companyAdmin']->id,
            'assigned_to_user_id' => $context['dentist']->id,
            'description' => 'Pozvati pacijenta za kontrolu.',
            'due_date' => '2026-06-01 00:00:00',
            'status' => PatientTask::STATUS_OPEN,
        ]);
    }

    public function test_company_admin_can_complete_a_patient_task(): void
    {
        $context = $this->createCompanyContext();
        $task = PatientTask::factory()->create([
            'company_id' => $context['company']->id,
            'patient_id' => $context['patient']->id,
            'created_by_user_id' => $context['companyAdmin']->id,
            'assigned_to_user_id' => $context['dentist']->id,
            'status' => PatientTask::STATUS_OPEN,
        ]);

        $this->actingAs($context['companyAdmin'])
            ->withSession(['_token' => self::CSRF_TOKEN])
            ->patch(route('patients.tasks.complete', [$context['patient']->id, $task->id]), $this->formData())
            ->assertRedirect();

        $this->assertDatabaseHas('patient_tasks', [
            'id' => $task->id,
            'status' => PatientTask::STATUS_DONE,
            'closed_by_user_id' => $context['companyAdmin']->id,
        ]);
    }

    public function test_company_admin_can_create_an_appointment(): void
    {
        $context = $this->createCompanyContext();

        $this->actingAs($context['companyAdmin'])
            ->withSession(['_token' => self::CSRF_TOKEN])
            ->post(route('patients.appointments.store', $context['patient']->id), $this->formData([
                'starts_at' => '2026-06-01 09:00:00',
                'ends_at' => '2026-06-01 09:30:00',
                'type' => Appointment::TYPE_CHECKUP,
                'assigned_user_id' => $context['dentist']->id,
                'notes' => 'Redovna kontrola.',
            ]))
            ->assertRedirect();

        $this->assertDatabaseHas('appointments', [
            'company_id' => $context['company']->id,
            'patient_id' => $context['patient']->id,
            'scheduled_by_user_id' => $context['companyAdmin']->id,
            'assigned_user_id' => $context['dentist']->id,
            'type' => Appointment::TYPE_CHECKUP,
            'status' => Appointment::STATUS_SCHEDULED,
            'notes' => 'Redovna kontrola.',
            'google_event_id' => null,
        ]);
    }

    public function test_company_admin_can_create_an_intervention(): void
    {
        $context = $this->createCompanyContext();

        $this->actingAs($context['companyAdmin'])
            ->withSession(['_token' => self::CSRF_TOKEN])
            ->post(route('patients.interventions.store', $context['patient']->id), $this->formData([
                'title' => 'Popravka zuba',
                'description' => 'Uradjena plomba.',
                'intervention_date' => '2026-06-01',
                'performed_by_user_id' => $context['dentist']->id,
                'total_cost' => '5000',
                'paid_amount' => '2000',
            ]))
            ->assertRedirect();

        $this->assertDatabaseHas('interventions', [
            'company_id' => $context['company']->id,
            'patient_id' => $context['patient']->id,
            'performed_by_user_id' => $context['dentist']->id,
            'title' => 'Popravka zuba',
            'description' => 'Uradjena plomba.',
            'intervention_date' => '2026-06-01 00:00:00',
            'total_cost' => 5000,
            'paid_amount' => 2000,
        ]);
    }

    public function test_intervention_with_next_step_creates_an_active_patient_task(): void
    {
        $context = $this->createCompanyContext();

        $this->actingAs($context['companyAdmin'])
            ->withSession(['_token' => self::CSRF_TOKEN])
            ->post(route('patients.interventions.store', $context['patient']->id), $this->formData([
                'title' => 'Kontrola kanala',
                'description' => 'Prva faza zavrsena.',
                'next_step' => 'Zakazati kontrolu za sedam dana.',
                'intervention_date' => '2026-06-01',
                'performed_by_user_id' => $context['dentist']->id,
                'assigned_to_user_id' => $context['dentist']->id,
                'task_due_date' => '2026-06-08',
                'total_cost' => '8000',
                'paid_amount' => '8000',
            ]))
            ->assertRedirect();

        $this->assertDatabaseHas('interventions', [
            'company_id' => $context['company']->id,
            'patient_id' => $context['patient']->id,
            'title' => 'Kontrola kanala',
            'next_step' => 'Zakazati kontrolu za sedam dana.',
        ]);

        $this->assertDatabaseHas('patient_tasks', [
            'company_id' => $context['company']->id,
            'patient_id' => $context['patient']->id,
            'created_by_user_id' => $context['companyAdmin']->id,
            'assigned_to_user_id' => $context['dentist']->id,
            'description' => 'Zakazati kontrolu za sedam dana.',
            'due_date' => '2026-06-08 00:00:00',
            'status' => PatientTask::STATUS_OPEN,
        ]);
    }

    /**
     * @return array{company: Company, companyAdmin: User, dentist: User, patient: Patient}
     */
    private function createCompanyContext(): array
    {
        $company = Company::factory()->create();
        $companyAdmin = User::factory()->companyAdmin()->forCompany($company)->create();
        $dentist = User::factory()->dentist()->forCompany($company)->create();
        $patient = Patient::factory()->create([
            'company_id' => $company->id,
            'primary_dentist_id' => $dentist->id,
        ]);

        return [
            'company' => $company,
            'companyAdmin' => $companyAdmin,
            'dentist' => $dentist,
            'patient' => $patient,
        ];
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    private function formData(array $data = []): array
    {
        return array_merge(['_token' => self::CSRF_TOKEN], $data);
    }
}
