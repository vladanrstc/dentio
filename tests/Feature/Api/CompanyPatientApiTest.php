<?php

namespace Tests\Feature\Api;

use App\Models\Appointment;
use App\Models\Company;
use App\Models\Intervention;
use App\Models\Patient;
use App\Models\PatientTask;
use App\Models\Reminder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CompanyPatientApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_user_can_view_patient_detail_from_own_company(): void
    {
        [$company, $user] = $this->companyUser();
        $patient = $this->patient($company);
        Appointment::query()->create([
            'company_id' => $company->id,
            'patient_id' => $patient->id,
            'scheduled_by_user_id' => $user->id,
            'starts_at' => '2026-05-12 10:00:00',
            'type' => Appointment::TYPE_CHECKUP,
            'status' => Appointment::STATUS_SCHEDULED,
        ]);
        Intervention::query()->create([
            'company_id' => $company->id,
            'patient_id' => $patient->id,
            'performed_by_user_id' => $user->id,
            'title' => 'Plomba',
            'intervention_date' => '2026-05-12',
            'total_cost' => 5000,
            'paid_amount' => 1500,
        ]);
        PatientTask::query()->create([
            'company_id' => $company->id,
            'patient_id' => $patient->id,
            'created_by_user_id' => $user->id,
            'description' => 'Kontrola',
            'status' => PatientTask::STATUS_OPEN,
        ]);

        Sanctum::actingAs($user);

        $this->getJson("/api/v1/company/patients/{$patient->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $patient->id)
            ->assertJsonPath('data.financials.total_cost', 5000)
            ->assertJsonPath('data.financials.paid_amount', 1500)
            ->assertJsonPath('data.financials.outstanding_amount', 3500)
            ->assertJsonStructure([
                'data' => [
                    'active_tasks',
                    'active_items',
                    'completed_tasks',
                    'appointments',
                    'interventions',
                    'financials',
                ],
            ]);
    }

    public function test_company_user_cannot_view_patient_detail_from_other_company(): void
    {
        [, $user] = $this->companyUser();
        [$otherCompany] = $this->companyUser('other');
        $otherPatient = $this->patient($otherCompany);

        Sanctum::actingAs($user);

        $this->getJson("/api/v1/company/patients/{$otherPatient->id}")
            ->assertNotFound()
            ->assertJsonMissingPath('data');
    }

    public function test_company_user_can_create_appointment_for_own_patient(): void
    {
        [$company, $user] = $this->companyUser();
        $patient = $this->patient($company);

        Sanctum::actingAs($user);

        $this->postJson("/api/v1/company/patients/{$patient->id}/appointments", [
            'starts_at' => '2026-05-12 10:00:00',
            'ends_at' => '2026-05-12 10:30:00',
            'type' => Appointment::TYPE_CHECKUP,
            'assigned_user_id' => $user->id,
            'notes' => 'Redovna kontrola',
        ])
            ->assertCreated()
            ->assertJsonPath('data.patient_id', $patient->id)
            ->assertJsonPath('data.type', Appointment::TYPE_CHECKUP)
            ->assertJsonPath('data.status', Appointment::STATUS_SCHEDULED);

        $this->assertDatabaseHas('appointments', [
            'company_id' => $company->id,
            'patient_id' => $patient->id,
            'assigned_user_id' => $user->id,
            'type' => Appointment::TYPE_CHECKUP,
        ]);
    }

    public function test_appointment_overlap_blocks_same_doctor(): void
    {
        [$company, $user] = $this->companyUser();
        $patient = $this->patient($company);
        $doctor = User::factory()->create([
            'company_id' => $company->id,
            'role' => User::ROLE_DENTIST,
        ]);
        Appointment::query()->create([
            'company_id' => $company->id,
            'patient_id' => $patient->id,
            'scheduled_by_user_id' => $user->id,
            'assigned_user_id' => $doctor->id,
            'starts_at' => '2026-05-12 10:00:00',
            'ends_at' => '2026-05-12 10:30:00',
            'type' => Appointment::TYPE_CHECKUP,
            'status' => Appointment::STATUS_SCHEDULED,
        ]);

        Sanctum::actingAs($user);

        $this->postJson("/api/v1/company/patients/{$patient->id}/appointments", [
            'starts_at' => '2026-05-12 10:15:00',
            'ends_at' => '2026-05-12 10:45:00',
            'type' => Appointment::TYPE_CHECKUP,
            'assigned_user_id' => $doctor->id,
        ])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Izabrani doktor već ima zakazan termin u tom periodu.');
    }

    public function test_different_doctor_can_have_appointment_at_same_time(): void
    {
        [$company, $user] = $this->companyUser();
        $patient = $this->patient($company);
        $firstDoctor = User::factory()->create([
            'company_id' => $company->id,
            'role' => User::ROLE_DENTIST,
        ]);
        $secondDoctor = User::factory()->create([
            'company_id' => $company->id,
            'role' => User::ROLE_DENTIST,
        ]);
        Appointment::query()->create([
            'company_id' => $company->id,
            'patient_id' => $patient->id,
            'scheduled_by_user_id' => $user->id,
            'assigned_user_id' => $firstDoctor->id,
            'starts_at' => '2026-05-12 10:00:00',
            'ends_at' => '2026-05-12 10:30:00',
            'type' => Appointment::TYPE_CHECKUP,
            'status' => Appointment::STATUS_SCHEDULED,
        ]);

        Sanctum::actingAs($user);

        $this->postJson("/api/v1/company/patients/{$patient->id}/appointments", [
            'starts_at' => '2026-05-12 10:00:00',
            'ends_at' => '2026-05-12 10:30:00',
            'type' => Appointment::TYPE_CHECKUP,
            'assigned_user_id' => $secondDoctor->id,
        ])
            ->assertCreated()
            ->assertJsonPath('data.assigned_to.id', $secondDoctor->id);
    }

    public function test_cancelled_appointment_does_not_block_new_appointment(): void
    {
        [$company, $user] = $this->companyUser();
        $patient = $this->patient($company);
        $doctor = User::factory()->create([
            'company_id' => $company->id,
            'role' => User::ROLE_DENTIST,
        ]);
        $appointment = Appointment::query()->create([
            'company_id' => $company->id,
            'patient_id' => $patient->id,
            'scheduled_by_user_id' => $user->id,
            'assigned_user_id' => $doctor->id,
            'starts_at' => '2026-05-12 10:00:00',
            'ends_at' => '2026-05-12 10:30:00',
            'type' => Appointment::TYPE_CHECKUP,
            'status' => Appointment::STATUS_SCHEDULED,
        ]);

        Sanctum::actingAs($user);

        $this->patchJson("/api/v1/company/appointments/{$appointment->id}/cancel", [
            'cancel_reason' => 'Pacijent je odlozio',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', Appointment::STATUS_CANCELLED)
            ->assertJsonPath('data.cancel_reason', 'Pacijent je odlozio');

        $this->postJson("/api/v1/company/patients/{$patient->id}/appointments", [
            'starts_at' => '2026-05-12 10:00:00',
            'ends_at' => '2026-05-12 10:30:00',
            'type' => Appointment::TYPE_CHECKUP,
            'assigned_user_id' => $doctor->id,
        ])->assertCreated();
    }

    public function test_company_user_can_create_intervention_for_own_patient(): void
    {
        [$company, $user] = $this->companyUser();
        $patient = $this->patient($company);

        Sanctum::actingAs($user);

        $this->postJson("/api/v1/company/patients/{$patient->id}/interventions", [
            'title' => 'Plomba',
            'description' => 'Gornja sestica',
            'intervention_date' => '2026-05-12',
            'performed_by_user_id' => $user->id,
            'total_cost' => 5000,
            'paid_amount' => 2000,
        ])
            ->assertCreated()
            ->assertJsonPath('data.patient_id', $patient->id)
            ->assertJsonPath('data.title', 'Plomba')
            ->assertJsonPath('data.outstanding_amount', 3000);

        $this->assertDatabaseHas('interventions', [
            'company_id' => $company->id,
            'patient_id' => $patient->id,
            'title' => 'Plomba',
        ]);
    }

    public function test_intervention_with_next_step_creates_patient_task(): void
    {
        [$company, $user] = $this->companyUser();
        $patient = $this->patient($company);

        Sanctum::actingAs($user);

        $this->postJson("/api/v1/company/patients/{$patient->id}/interventions", [
            'title' => 'Vadjenje zuba',
            'intervention_date' => '2026-05-12',
            'performed_by_user_id' => $user->id,
            'next_step' => 'Zakazati kontrolu',
            'assigned_to_user_id' => $user->id,
            'task_due_date' => '2026-05-20',
            'total_cost' => 3000,
            'paid_amount' => 3000,
        ])->assertCreated();

        $this->assertDatabaseHas('patient_tasks', [
            'company_id' => $company->id,
            'patient_id' => $patient->id,
            'assigned_to_user_id' => $user->id,
            'description' => 'Zakazati kontrolu',
            'status' => PatientTask::STATUS_OPEN,
        ]);
    }

    public function test_company_user_can_create_patient_task(): void
    {
        [$company, $user] = $this->companyUser();
        $patient = $this->patient($company);

        Sanctum::actingAs($user);

        $this->postJson("/api/v1/company/patients/{$patient->id}/tasks", [
            'description' => 'Pozvati pacijenta',
            'due_date' => '2026-05-20',
            'assigned_to_user_id' => $user->id,
        ])
            ->assertCreated()
            ->assertJsonPath('data.patient_id', $patient->id)
            ->assertJsonPath('data.description', 'Pozvati pacijenta')
            ->assertJsonPath('data.status', PatientTask::STATUS_OPEN);

        $this->assertDatabaseHas('patient_tasks', [
            'company_id' => $company->id,
            'patient_id' => $patient->id,
            'description' => 'Pozvati pacijenta',
        ]);
    }

    public function test_company_user_can_complete_task_for_own_patient(): void
    {
        [$company, $user] = $this->companyUser();
        $patient = $this->patient($company);
        $task = PatientTask::query()->create([
            'company_id' => $company->id,
            'patient_id' => $patient->id,
            'created_by_user_id' => $user->id,
            'description' => 'Pozvati pacijenta',
            'status' => PatientTask::STATUS_OPEN,
        ]);

        Sanctum::actingAs($user);

        $this->patchJson("/api/v1/company/patients/{$patient->id}/tasks/{$task->id}/complete")
            ->assertOk()
            ->assertJsonPath('data.id', $task->id)
            ->assertJsonPath('data.status', PatientTask::STATUS_DONE);

        $this->assertDatabaseHas('patient_tasks', [
            'id' => $task->id,
            'status' => PatientTask::STATUS_DONE,
            'closed_by_user_id' => $user->id,
        ]);
    }

    public function test_company_user_can_change_patient_status(): void
    {
        [$company, $user] = $this->companyUser();
        $patient = $this->patient($company);

        Sanctum::actingAs($user);

        $this->patchJson("/api/v1/company/patients/{$patient->id}/status", [
            'manual_status' => Patient::STATUS_INACTIVE,
            'manual_status_reason' => 'Pacijent pauzira terapiju',
        ])
            ->assertOk()
            ->assertJsonPath('data.id', $patient->id)
            ->assertJsonPath('data.manual_status', Patient::STATUS_INACTIVE)
            ->assertJsonPath('data.manual_status_reason', 'Pacijent pauzira terapiju');

        $this->assertDatabaseHas('patient_status_logs', [
            'company_id' => $company->id,
            'patient_id' => $patient->id,
            'changed_by_user_id' => $user->id,
            'previous_status' => Patient::STATUS_ACTIVE,
            'new_status' => Patient::STATUS_INACTIVE,
        ]);
    }

    public function test_company_user_cannot_change_patient_or_task_from_other_company(): void
    {
        [, $user] = $this->companyUser();
        [$otherCompany, $otherUser] = $this->companyUser('other');
        $otherPatient = $this->patient($otherCompany);
        $otherTask = PatientTask::query()->create([
            'company_id' => $otherCompany->id,
            'patient_id' => $otherPatient->id,
            'created_by_user_id' => $otherUser->id,
            'description' => 'Tudji task',
            'status' => PatientTask::STATUS_OPEN,
        ]);

        Sanctum::actingAs($user);

        $this->patchJson("/api/v1/company/patients/{$otherPatient->id}/status", [
            'manual_status' => Patient::STATUS_COMPLETED,
        ])->assertNotFound();

        $this->patchJson("/api/v1/company/patients/{$otherPatient->id}/tasks/{$otherTask->id}/complete")
            ->assertNotFound();

        $this->assertDatabaseHas('patients', [
            'id' => $otherPatient->id,
            'manual_status' => Patient::STATUS_ACTIVE,
        ]);
        $this->assertDatabaseHas('patient_tasks', [
            'id' => $otherTask->id,
            'status' => PatientTask::STATUS_OPEN,
        ]);
    }

    public function test_validation_errors_return_422_json_response(): void
    {
        [$company, $user] = $this->companyUser();
        $patient = $this->patient($company);

        Sanctum::actingAs($user);

        $this->postJson("/api/v1/company/patients/{$patient->id}/appointments", [
            'starts_at' => null,
            'type' => 'unknown',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['starts_at', 'type'])
            ->assertHeader('content-type', 'application/json');

        $this->postJson("/api/v1/company/patients/{$patient->id}/appointments", [
            'starts_at' => '2026-05-12 10:00:00',
            'ends_at' => '2026-05-12 09:00:00',
            'type' => Appointment::TYPE_CHECKUP,
        ])
            ->assertUnprocessable()
            ->assertJsonPath('errors.ends_at.0', 'Kraj termina mora biti posle početka termina.')
            ->assertHeader('content-type', 'application/json');
    }

    public function test_unauthorized_request_returns_401_json_response(): void
    {
        [$company] = $this->companyUser();
        $patient = $this->patient($company);

        $this->getJson("/api/v1/company/patients/{$patient->id}")
            ->assertUnauthorized()
            ->assertHeader('content-type', 'application/json')
            ->assertJsonMissingPath('data');
    }

    public function test_deleting_patient_removes_related_reminders_from_dashboard(): void
    {
        [$company, $user] = $this->companyUser();
        $patient = $this->patient($company);
        $appointment = Appointment::query()->create([
            'company_id' => $company->id,
            'patient_id' => $patient->id,
            'scheduled_by_user_id' => $user->id,
            'starts_at' => '2026-05-12 10:00:00',
            'type' => Appointment::TYPE_CHECKUP,
            'status' => Appointment::STATUS_SCHEDULED,
        ]);

        $intervention = Intervention::query()->create([
            'company_id' => $company->id,
            'patient_id' => $patient->id,
            'appointment_id' => $appointment->id,
            'performed_by_user_id' => $user->id,
            'title' => 'Kontrola',
            'intervention_date' => '2026-05-12',
        ]);

        PatientTask::query()->create([
            'company_id' => $company->id,
            'patient_id' => $patient->id,
            'created_by_user_id' => $user->id,
            'description' => 'Pozvati pacijenta',
            'status' => PatientTask::STATUS_OPEN,
        ]);

        Reminder::query()->create([
            'company_id' => $company->id,
            'patient_id' => $patient->id,
            'recipient_email' => 'patient-reminder@example.com',
            'recipient_type' => Reminder::TYPE_PATIENT,
            'remind_at' => Carbon::now()->subMinute(),
            'subject' => 'Direktan podsetnik',
            'body' => 'Test podsetnik',
            'status' => Reminder::STATUS_PENDING,
        ]);
        Reminder::query()->create([
            'company_id' => $company->id,
            'appointment_id' => $appointment->id,
            'recipient_email' => 'appointment-reminder@example.com',
            'recipient_type' => Reminder::TYPE_PATIENT,
            'remind_at' => Carbon::now()->subMinute(),
            'subject' => 'Termin podsetnik',
            'body' => 'Test podsetnik',
            'status' => Reminder::STATUS_PENDING,
        ]);
        Reminder::query()->create([
            'company_id' => $company->id,
            'intervention_id' => $intervention->id,
            'recipient_email' => 'intervention-reminder@example.com',
            'recipient_type' => Reminder::TYPE_PATIENT,
            'remind_at' => Carbon::now()->subMinute(),
            'subject' => 'Intervencija podsetnik',
            'body' => 'Test podsetnik',
            'status' => Reminder::STATUS_PENDING,
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/dashboard')
            ->assertOk()
            ->assertJsonPath('data.reminders_due', 3);

        $this->deleteJson("/api/v1/company/patients/{$patient->id}")
            ->assertOk();

        $this->getJson('/api/v1/dashboard')
            ->assertOk()
            ->assertJsonPath('data.reminders_due', 0);

        $this->assertDatabaseMissing('patients', [
            'id' => $patient->id,
        ]);
        $this->assertDatabaseMissing('reminders', [
            'patient_id' => $patient->id,
        ]);
        $this->assertDatabaseMissing('appointments', [
            'patient_id' => $patient->id,
        ]);
        $this->assertDatabaseMissing('interventions', [
            'patient_id' => $patient->id,
        ]);
        $this->assertDatabaseMissing('patient_tasks', [
            'patient_id' => $patient->id,
        ]);
    }

    /**
     * @return array{Company, User}
     */
    private function companyUser(string $suffix = 'main'): array
    {
        $company = Company::query()->create([
            'name' => "Company {$suffix}",
            'address' => "Address {$suffix}",
            'email' => "{$suffix}@example.com",
            'phone' => '060123456',
        ]);

        $user = User::factory()->create([
            'company_id' => $company->id,
            'name' => "Dr {$suffix}",
            'first_name' => 'Dr',
            'last_name' => ucfirst($suffix),
            'role' => User::ROLE_COMPANY_ADMIN,
        ]);

        return [$company, $user];
    }

    private function patient(Company $company): Patient
    {
        return Patient::query()->create([
            'company_id' => $company->id,
            'first_name' => 'Marko',
            'last_name' => 'Markovic',
            'address' => 'Test adresa',
            'phone' => '060111222',
            'email' => 'marko@example.com',
            'manual_status' => Patient::STATUS_ACTIVE,
        ]);
    }
}
