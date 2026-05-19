<?php

namespace Tests\Feature\Patients;

use App\Models\Appointment;
use App\Models\Company;
use App\Models\Intervention;
use App\Models\Patient;
use App\Models\PatientTask;
use App\Models\Reminder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ReminderCreationTest extends TestCase
{
    use RefreshDatabase;

    private const CSRF_TOKEN = 'test-token';

    public function test_creating_appointment_without_reminder_fields_creates_no_reminders(): void
    {
        Mail::fake();
        $context = $this->createCompanyContext();

        $this->actingAs($context['companyAdmin'])
            ->withSession(['_token' => self::CSRF_TOKEN])
            ->post(route('patients.appointments.store', $context['patient']->id), $this->formData([
                'starts_at' => '2026-06-01 09:00:00',
                'ends_at' => '2026-06-01 09:30:00',
                'type' => Appointment::TYPE_CHECKUP,
                'assigned_user_id' => $context['dentist']->id,
                'notes' => 'Bez podsetnika.',
            ]))
            ->assertRedirect();

        $this->assertDatabaseHas('appointments', [
            'company_id' => $context['company']->id,
            'patient_id' => $context['patient']->id,
            'notes' => 'Bez podsetnika.',
            'google_event_id' => null,
        ]);
        $this->assertDatabaseCount('reminders', 0);
    }

    public function test_creating_appointment_with_staff_reminder_creates_pending_staff_reminder(): void
    {
        Mail::fake();
        $context = $this->createCompanyContext();

        $this->actingAs($context['companyAdmin'])
            ->withSession(['_token' => self::CSRF_TOKEN])
            ->post(route('patients.appointments.store', $context['patient']->id), $this->formData([
                'starts_at' => '2026-06-01 09:00:00',
                'ends_at' => '2026-06-01 09:30:00',
                'type' => Appointment::TYPE_CHECKUP,
                'assigned_user_id' => $context['dentist']->id,
                'reminder_staff_at' => '2026-05-31 09:00:00',
            ]))
            ->assertRedirect();

        $appointment = Appointment::query()->where('patient_id', $context['patient']->id)->firstOrFail();

        $this->assertNull($appointment->google_event_id);
        $this->assertDatabaseHas('reminders', [
            'company_id' => $context['company']->id,
            'patient_id' => $context['patient']->id,
            'appointment_id' => $appointment->id,
            'intervention_id' => null,
            'recipient_email' => $context['dentist']->email,
            'recipient_type' => Reminder::TYPE_STAFF,
            'remind_at' => '2026-05-31 09:00:00',
            'status' => Reminder::STATUS_PENDING,
        ]);
    }

    public function test_creating_appointment_with_patient_reminder_creates_pending_patient_reminder_when_patient_has_email(): void
    {
        Mail::fake();
        $context = $this->createCompanyContext();

        $this->actingAs($context['companyAdmin'])
            ->withSession(['_token' => self::CSRF_TOKEN])
            ->post(route('patients.appointments.store', $context['patient']->id), $this->formData([
                'starts_at' => '2026-06-01 09:00:00',
                'ends_at' => '2026-06-01 09:30:00',
                'type' => Appointment::TYPE_CHECKUP,
                'assigned_user_id' => $context['dentist']->id,
                'reminder_patient_at' => '2026-05-31 10:00:00',
            ]))
            ->assertRedirect();

        $appointment = Appointment::query()->where('patient_id', $context['patient']->id)->firstOrFail();

        $this->assertNull($appointment->google_event_id);
        $this->assertDatabaseHas('reminders', [
            'company_id' => $context['company']->id,
            'patient_id' => $context['patient']->id,
            'appointment_id' => $appointment->id,
            'intervention_id' => null,
            'recipient_email' => $context['patient']->email,
            'recipient_type' => Reminder::TYPE_PATIENT,
            'remind_at' => '2026-05-31 10:00:00',
            'status' => Reminder::STATUS_PENDING,
        ]);
    }

    public function test_creating_appointment_with_patient_reminder_does_not_create_patient_reminder_without_patient_email(): void
    {
        Mail::fake();
        $context = $this->createCompanyContext(['patient_email' => null]);

        $this->actingAs($context['companyAdmin'])
            ->withSession(['_token' => self::CSRF_TOKEN])
            ->post(route('patients.appointments.store', $context['patient']->id), $this->formData([
                'starts_at' => '2026-06-01 09:00:00',
                'ends_at' => '2026-06-01 09:30:00',
                'type' => Appointment::TYPE_CHECKUP,
                'assigned_user_id' => $context['dentist']->id,
                'reminder_patient_at' => '2026-05-31 10:00:00',
            ]))
            ->assertRedirect();

        $this->assertDatabaseHas('appointments', [
            'company_id' => $context['company']->id,
            'patient_id' => $context['patient']->id,
            'google_event_id' => null,
        ]);
        $this->assertDatabaseCount('reminders', 0);
    }

    public function test_creating_intervention_with_staff_reminder_creates_pending_staff_reminder(): void
    {
        Mail::fake();
        $context = $this->createCompanyContext();

        $this->actingAs($context['companyAdmin'])
            ->withSession(['_token' => self::CSRF_TOKEN])
            ->post(route('patients.interventions.store', $context['patient']->id), $this->formData([
                'title' => 'Kontrola',
                'description' => 'Uradjena kontrola.',
                'intervention_date' => '2026-06-01',
                'performed_by_user_id' => $context['dentist']->id,
                'total_cost' => '4000',
                'paid_amount' => '1000',
                'reminder_staff_at' => '2026-06-07 09:00:00',
            ]))
            ->assertRedirect();

        $intervention = Intervention::query()->where('patient_id', $context['patient']->id)->firstOrFail();

        $this->assertDatabaseHas('reminders', [
            'company_id' => $context['company']->id,
            'patient_id' => $context['patient']->id,
            'appointment_id' => null,
            'intervention_id' => $intervention->id,
            'recipient_email' => $context['companyAdmin']->email,
            'recipient_type' => Reminder::TYPE_STAFF,
            'remind_at' => '2026-06-07 09:00:00',
            'status' => Reminder::STATUS_PENDING,
        ]);
    }

    public function test_creating_intervention_with_patient_reminder_creates_pending_patient_reminder_when_patient_has_email(): void
    {
        Mail::fake();
        $context = $this->createCompanyContext();

        $this->actingAs($context['companyAdmin'])
            ->withSession(['_token' => self::CSRF_TOKEN])
            ->post(route('patients.interventions.store', $context['patient']->id), $this->formData([
                'title' => 'Terapija',
                'description' => 'Uradjena terapija.',
                'intervention_date' => '2026-06-01',
                'performed_by_user_id' => $context['dentist']->id,
                'total_cost' => '6000',
                'paid_amount' => '3000',
                'reminder_patient_at' => '2026-06-07 10:00:00',
            ]))
            ->assertRedirect();

        $intervention = Intervention::query()->where('patient_id', $context['patient']->id)->firstOrFail();

        $this->assertDatabaseHas('reminders', [
            'company_id' => $context['company']->id,
            'patient_id' => $context['patient']->id,
            'appointment_id' => null,
            'intervention_id' => $intervention->id,
            'recipient_email' => $context['patient']->email,
            'recipient_type' => Reminder::TYPE_PATIENT,
            'remind_at' => '2026-06-07 10:00:00',
            'status' => Reminder::STATUS_PENDING,
        ]);
    }

    public function test_intervention_next_step_creates_patient_task_while_reminder_fields_are_present(): void
    {
        Mail::fake();
        $context = $this->createCompanyContext();

        $this->actingAs($context['companyAdmin'])
            ->withSession(['_token' => self::CSRF_TOKEN])
            ->post(route('patients.interventions.store', $context['patient']->id), $this->formData([
                'title' => 'Endodoncija',
                'description' => 'Prva faza.',
                'next_step' => 'Zakazati kontrolu.',
                'intervention_date' => '2026-06-01',
                'performed_by_user_id' => $context['dentist']->id,
                'assigned_to_user_id' => $context['dentist']->id,
                'task_due_date' => '2026-06-08',
                'total_cost' => '9000',
                'paid_amount' => '5000',
                'reminder_staff_at' => '2026-06-07 09:00:00',
                'reminder_patient_at' => '2026-06-07 10:00:00',
            ]))
            ->assertRedirect();

        $this->assertDatabaseHas('patient_tasks', [
            'company_id' => $context['company']->id,
            'patient_id' => $context['patient']->id,
            'created_by_user_id' => $context['companyAdmin']->id,
            'assigned_to_user_id' => $context['dentist']->id,
            'description' => 'Zakazati kontrolu.',
            'due_date' => '2026-06-08 00:00:00',
            'status' => PatientTask::STATUS_OPEN,
        ]);
        $this->assertDatabaseCount('reminders', 2);
    }

    /**
     * @param array{patient_email?: string|null} $overrides
     *
     * @return array{company: Company, companyAdmin: User, dentist: User, patient: Patient}
     */
    private function createCompanyContext(array $overrides = []): array
    {
        $company = Company::factory()->create();
        $companyAdmin = User::factory()->companyAdmin()->forCompany($company)->create();
        $dentist = User::factory()->dentist()->forCompany($company)->create();
        $patient = Patient::factory()->create([
            'company_id' => $company->id,
            'primary_dentist_id' => $dentist->id,
            'email' => array_key_exists('patient_email', $overrides)
                ? $overrides['patient_email']
                : 'patient@example.com',
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
