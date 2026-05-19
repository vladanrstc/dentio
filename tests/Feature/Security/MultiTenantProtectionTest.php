<?php

namespace Tests\Feature\Security;

use App\Models\Appointment;
use App\Models\Company;
use App\Models\Intervention;
use App\Models\Patient;
use App\Models\PatientStatusLog;
use App\Models\PatientTask;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class MultiTenantProtectionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(PreventRequestForgery::class);
    }

    public function test_company_user_cannot_view_other_company_patient_details(): void
    {
        $data = $this->tenantData();

        $response = $this->actingAs($data['userA'])
            ->get(route('patients.show', $data['patientB']->id));

        $this->assertBlocked($response);
    }

    public function test_company_user_cannot_open_other_company_patient_edit_page(): void
    {
        $data = $this->tenantData();

        $response = $this->actingAs($data['userA'])
            ->get(route('patients.edit', $data['patientB']->id));

        $this->assertBlocked($response);
    }

    public function test_company_user_cannot_update_other_company_patient(): void
    {
        $data = $this->tenantData();

        $response = $this->actingAs($data['userA'])
            ->put(route('patients.update', $data['patientB']->id), $this->patientPayload([
                'first_name' => 'Changed',
            ]));

        $this->assertBlocked($response);

        $this->assertDatabaseHas('patients', [
            'id' => $data['patientB']->id,
            'first_name' => $data['patientB']->first_name,
            'company_id' => $data['companyB']->id,
        ]);
    }

    public function test_company_user_cannot_change_other_company_patient_status(): void
    {
        $data = $this->tenantData();

        $response = $this->actingAs($data['userA'])
            ->patch(route('patients.status.update', $data['patientB']->id), [
                'manual_status' => Patient::STATUS_INACTIVE,
                'manual_status_reason' => 'Cross tenant attempt',
            ]);

        $this->assertBlocked($response);

        $this->assertDatabaseHas('patients', [
            'id' => $data['patientB']->id,
            'manual_status' => Patient::STATUS_ACTIVE,
        ]);
        $this->assertSame(0, PatientStatusLog::query()->count());
    }

    public function test_company_user_cannot_create_task_for_other_company_patient(): void
    {
        $data = $this->tenantData();

        $response = $this->actingAs($data['userA'])
            ->post(route('patients.tasks.store', $data['patientB']->id), [
                'description' => 'Cross tenant task',
                'due_date' => Carbon::tomorrow()->toDateString(),
                'assigned_to_user_id' => $data['userA']->id,
            ]);

        $this->assertBlocked($response);

        $this->assertDatabaseMissing('patient_tasks', [
            'patient_id' => $data['patientB']->id,
            'description' => 'Cross tenant task',
        ]);
    }

    public function test_company_user_cannot_create_appointment_for_other_company_patient(): void
    {
        $data = $this->tenantData();

        $response = $this->actingAs($data['userA'])
            ->post(route('patients.appointments.store', $data['patientB']->id), $this->appointmentPayload([
                'assigned_user_id' => $data['userA']->id,
            ]));

        $this->assertBlocked($response);

        $this->assertDatabaseMissing('appointments', [
            'patient_id' => $data['patientB']->id,
            'scheduled_by_user_id' => $data['userA']->id,
        ]);
    }

    public function test_company_user_cannot_create_intervention_for_other_company_patient(): void
    {
        $data = $this->tenantData();

        $response = $this->actingAs($data['userA'])
            ->post(route('patients.interventions.store', $data['patientB']->id), $this->interventionPayload([
                'performed_by_user_id' => $data['userA']->id,
            ]));

        $this->assertBlocked($response);

        $this->assertDatabaseMissing('interventions', [
            'patient_id' => $data['patientB']->id,
            'title' => 'Security intervention',
        ]);
    }

    public function test_company_user_cannot_complete_other_company_patient_task(): void
    {
        $data = $this->tenantData();

        $response = $this->actingAs($data['userA'])
            ->patch(route('patients.tasks.complete', [$data['patientB']->id, $data['taskB']->id]));

        $this->assertBlocked($response);

        $this->assertDatabaseHas('patient_tasks', [
            'id' => $data['taskB']->id,
            'status' => PatientTask::STATUS_OPEN,
            'closed_by_user_id' => null,
        ]);
    }

    public function test_non_numeric_patient_route_value_returns_not_found(): void
    {
        $data = $this->tenantData();

        $response = $this->actingAs($data['userA'])->get('/patients/not-a-number');

        $response->assertNotFound();
    }

    public function test_same_company_patient_route_resolves_successfully(): void
    {
        $data = $this->tenantData();

        $response = $this->actingAs($data['userA'])
            ->get(route('patients.show', $data['patientA']->id));

        $response->assertOk();
    }

    public function test_other_company_patient_route_returns_not_found(): void
    {
        $data = $this->tenantData();

        $response = $this->actingAs($data['userA'])
            ->get(route('patients.show', $data['patientB']->id));

        $response->assertNotFound();
    }

    public function test_same_company_task_for_correct_patient_resolves_successfully(): void
    {
        $data = $this->tenantData();

        $response = $this->actingAs($data['userA'])
            ->patch(route('patients.tasks.complete', [$data['patientA']->id, $data['taskA']->id]));

        $response->assertRedirect();
        $this->assertDatabaseHas('patient_tasks', [
            'id' => $data['taskA']->id,
            'status' => PatientTask::STATUS_DONE,
            'closed_by_user_id' => $data['userA']->id,
        ]);
    }

    public function test_same_company_task_under_different_patient_returns_not_found(): void
    {
        $data = $this->tenantData();

        $response = $this->actingAs($data['userA'])
            ->patch(route('patients.tasks.complete', [$data['otherPatientA']->id, $data['taskA']->id]));

        $response->assertNotFound();
        $this->assertDatabaseHas('patient_tasks', [
            'id' => $data['taskA']->id,
            'status' => PatientTask::STATUS_OPEN,
            'closed_by_user_id' => null,
        ]);
    }

    public function test_other_company_task_route_returns_not_found(): void
    {
        $data = $this->tenantData();

        $response = $this->actingAs($data['userA'])
            ->patch(route('patients.tasks.complete', [$data['patientA']->id, $data['taskB']->id]));

        $response->assertNotFound();
        $this->assertDatabaseHas('patient_tasks', [
            'id' => $data['taskB']->id,
            'status' => PatientTask::STATUS_OPEN,
            'closed_by_user_id' => null,
        ]);
    }

    public function test_company_user_cannot_assign_other_company_primary_dentist_when_creating_patient(): void
    {
        $data = $this->tenantData();
        $countBefore = Patient::query()->count();

        $response = $this->actingAs($data['userA'])
            ->post(route('patients.store'), $this->patientPayload([
                'primary_dentist_id' => $data['userB']->id,
            ]));

        $this->assertBlockedOrValidationError($response);
        $this->assertSame($countBefore, Patient::query()->count());
    }

    public function test_company_user_cannot_assign_other_company_primary_dentist_when_updating_patient(): void
    {
        $data = $this->tenantData();

        $response = $this->actingAs($data['userA'])
            ->put(route('patients.update', $data['patientA']->id), $this->patientPayload([
                'primary_dentist_id' => $data['userB']->id,
            ]));

        $this->assertBlockedOrValidationError($response);
        $this->assertDatabaseHas('patients', [
            'id' => $data['patientA']->id,
            'primary_dentist_id' => $data['userA']->id,
        ]);
    }

    public function test_company_user_cannot_assign_or_use_other_company_users(): void
    {
        $data = $this->tenantData();

        $appointmentResponse = $this->actingAs($data['userA'])
            ->post(route('patients.appointments.store', $data['patientA']->id), $this->appointmentPayload([
                'assigned_user_id' => $data['userB']->id,
            ]));
        $this->assertBlockedOrValidationError($appointmentResponse);

        $taskResponse = $this->actingAs($data['userA'])
            ->post(route('patients.tasks.store', $data['patientA']->id), [
                'description' => 'Invalid assignee task',
                'due_date' => Carbon::tomorrow()->toDateString(),
                'assigned_to_user_id' => $data['userB']->id,
            ]);
        $this->assertBlockedOrValidationError($taskResponse);

        $interventionResponse = $this->actingAs($data['userA'])
            ->post(route('patients.interventions.store', $data['patientA']->id), $this->interventionPayload([
                'performed_by_user_id' => $data['userB']->id,
                'assigned_to_user_id' => $data['userB']->id,
            ]));
        $this->assertBlockedOrValidationError($interventionResponse);

        $this->assertDatabaseMissing('appointments', [
            'patient_id' => $data['patientA']->id,
            'assigned_user_id' => $data['userB']->id,
        ]);
        $this->assertDatabaseMissing('patient_tasks', [
            'patient_id' => $data['patientA']->id,
            'assigned_to_user_id' => $data['userB']->id,
        ]);
        $this->assertDatabaseMissing('interventions', [
            'patient_id' => $data['patientA']->id,
            'performed_by_user_id' => $data['userB']->id,
        ]);
    }

    public function test_company_user_cannot_use_other_company_appointment_when_creating_intervention(): void
    {
        $data = $this->tenantData();

        $response = $this->actingAs($data['userA'])
            ->post(route('patients.interventions.store', $data['patientA']->id), $this->interventionPayload([
                'appointment_id' => $data['appointmentB']->id,
            ]));

        $this->assertBlockedOrValidationError($response);
        $this->assertDatabaseMissing('interventions', [
            'patient_id' => $data['patientA']->id,
            'appointment_id' => $data['appointmentB']->id,
        ]);
    }

    public function test_company_user_cannot_use_same_company_different_patient_appointment_when_creating_intervention(): void
    {
        $data = $this->tenantData();

        $response = $this->actingAs($data['userA'])
            ->post(route('patients.interventions.store', $data['patientA']->id), $this->interventionPayload([
                'appointment_id' => $data['otherPatientAAppointment']->id,
            ]));

        $this->assertBlockedOrValidationError($response);
        $this->assertDatabaseMissing('interventions', [
            'patient_id' => $data['patientA']->id,
            'appointment_id' => $data['otherPatientAAppointment']->id,
        ]);
    }

    public function test_same_company_user_can_create_patient_with_same_company_primary_dentist(): void
    {
        $data = $this->tenantData();

        $response = $this->actingAs($data['userA'])
            ->post(route('patients.store'), $this->patientPayload([
                'primary_dentist_id' => $data['userA']->id,
            ]));

        $response->assertRedirect();
        $this->assertDatabaseHas('patients', [
            'company_id' => $data['companyA']->id,
            'first_name' => 'Safe',
            'last_name' => 'Patient',
            'primary_dentist_id' => $data['userA']->id,
        ]);
    }

    public function test_same_company_user_can_update_patient_with_same_company_primary_dentist(): void
    {
        $data = $this->tenantData();

        $response = $this->actingAs($data['userA'])
            ->put(route('patients.update', $data['patientA']->id), $this->patientPayload([
                'first_name' => 'Updated',
                'primary_dentist_id' => $data['userA']->id,
            ]));

        $response->assertRedirect();
        $this->assertDatabaseHas('patients', [
            'id' => $data['patientA']->id,
            'company_id' => $data['companyA']->id,
            'first_name' => 'Updated',
            'primary_dentist_id' => $data['userA']->id,
        ]);
    }

    public function test_same_company_user_can_change_patient_status(): void
    {
        $data = $this->tenantData();

        $response = $this->actingAs($data['userA'])
            ->patch(route('patients.status.update', $data['patientA']->id), [
                'manual_status' => Patient::STATUS_INACTIVE,
                'manual_status_reason' => 'Follow up later',
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('patients', [
            'id' => $data['patientA']->id,
            'manual_status' => Patient::STATUS_INACTIVE,
            'manual_status_reason' => 'Follow up later',
            'manual_status_changed_by_user_id' => $data['userA']->id,
        ]);
        $this->assertDatabaseHas('patient_status_logs', [
            'company_id' => $data['companyA']->id,
            'patient_id' => $data['patientA']->id,
            'changed_by_user_id' => $data['userA']->id,
            'previous_status' => Patient::STATUS_ACTIVE,
            'new_status' => Patient::STATUS_INACTIVE,
        ]);
    }

    public function test_same_company_user_can_create_task_with_same_company_assigned_user(): void
    {
        $data = $this->tenantData();

        $response = $this->actingAs($data['userA'])
            ->post(route('patients.tasks.store', $data['patientA']->id), [
                'description' => 'Same company task',
                'due_date' => Carbon::tomorrow()->toDateString(),
                'assigned_to_user_id' => $data['userA']->id,
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('patient_tasks', [
            'company_id' => $data['companyA']->id,
            'patient_id' => $data['patientA']->id,
            'created_by_user_id' => $data['userA']->id,
            'assigned_to_user_id' => $data['userA']->id,
            'description' => 'Same company task',
            'status' => PatientTask::STATUS_OPEN,
        ]);
    }

    public function test_same_company_user_can_create_appointment_with_same_company_responsible_user(): void
    {
        $data = $this->tenantData();

        $response = $this->actingAs($data['userA'])
            ->post(route('patients.appointments.store', $data['patientA']->id), $this->appointmentPayload([
                'assigned_user_id' => $data['userA']->id,
            ]));

        $response->assertRedirect();
        $this->assertDatabaseHas('appointments', [
            'company_id' => $data['companyA']->id,
            'patient_id' => $data['patientA']->id,
            'scheduled_by_user_id' => $data['userA']->id,
            'assigned_user_id' => $data['userA']->id,
            'type' => Appointment::TYPE_CHECKUP,
            'status' => Appointment::STATUS_SCHEDULED,
        ]);
    }

    public function test_same_company_user_can_create_intervention_with_same_company_appointment_and_performer(): void
    {
        $data = $this->tenantData();

        $response = $this->actingAs($data['userA'])
            ->post(route('patients.interventions.store', $data['patientA']->id), $this->interventionPayload([
                'appointment_id' => $data['appointmentA']->id,
                'performed_by_user_id' => $data['userA']->id,
            ]));

        $response->assertRedirect();
        $this->assertDatabaseHas('interventions', [
            'company_id' => $data['companyA']->id,
            'patient_id' => $data['patientA']->id,
            'appointment_id' => $data['appointmentA']->id,
            'performed_by_user_id' => $data['userA']->id,
            'title' => 'Security intervention',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function tenantData(): array
    {
        $companyA = Company::query()->create([
            'name' => 'Company A',
            'address' => 'A Street',
            'email' => 'a@example.com',
        ]);
        $companyB = Company::query()->create([
            'name' => 'Company B',
            'address' => 'B Street',
            'email' => 'b@example.com',
        ]);

        $userA = User::factory()->create([
            'company_id' => $companyA->id,
            'role' => User::ROLE_DENTIST,
            'first_name' => 'Alice',
            'last_name' => 'Dentist',
        ]);
        $userB = User::factory()->create([
            'company_id' => $companyB->id,
            'role' => User::ROLE_DENTIST,
            'first_name' => 'Bob',
            'last_name' => 'Dentist',
        ]);

        $patientA = Patient::query()->create($this->patientModelPayload($companyA->id, $userA->id, 'Alice Patient'));
        $otherPatientA = Patient::query()->create($this->patientModelPayload($companyA->id, $userA->id, 'Other Patient'));
        $patientB = Patient::query()->create($this->patientModelPayload($companyB->id, $userB->id, 'Bob Patient'));

        $appointmentA = Appointment::query()->create($this->appointmentModelPayload($companyA->id, $patientA->id, $userA->id));
        $otherPatientAAppointment = Appointment::query()->create($this->appointmentModelPayload($companyA->id, $otherPatientA->id, $userA->id));
        $appointmentB = Appointment::query()->create($this->appointmentModelPayload($companyB->id, $patientB->id, $userB->id));

        $taskA = PatientTask::query()->create($this->taskModelPayload($companyA->id, $patientA->id, $userA->id));
        $taskB = PatientTask::query()->create($this->taskModelPayload($companyB->id, $patientB->id, $userB->id));

        return compact(
            'companyA',
            'companyB',
            'userA',
            'userB',
            'patientA',
            'otherPatientA',
            'patientB',
            'appointmentA',
            'otherPatientAAppointment',
            'appointmentB',
            'taskA',
            'taskB',
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function patientModelPayload(int $companyId, int $dentistId, string $name): array
    {
        return [
            'company_id' => $companyId,
            'primary_dentist_id' => $dentistId,
            'first_name' => $name,
            'last_name' => 'Test',
            'address' => 'Test Street',
            'phone' => '123',
            'email' => null,
            'manual_status' => Patient::STATUS_ACTIVE,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function appointmentModelPayload(int $companyId, int $patientId, int $userId): array
    {
        return [
            'company_id' => $companyId,
            'patient_id' => $patientId,
            'scheduled_by_user_id' => $userId,
            'assigned_user_id' => $userId,
            'starts_at' => Carbon::tomorrow()->setTime(9, 0),
            'ends_at' => Carbon::tomorrow()->setTime(9, 30),
            'type' => Appointment::TYPE_CHECKUP,
            'status' => Appointment::STATUS_SCHEDULED,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function taskModelPayload(int $companyId, int $patientId, int $userId): array
    {
        return [
            'company_id' => $companyId,
            'patient_id' => $patientId,
            'created_by_user_id' => $userId,
            'assigned_to_user_id' => $userId,
            'description' => 'Existing task',
            'due_date' => Carbon::tomorrow()->toDateString(),
            'status' => PatientTask::STATUS_OPEN,
        ];
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function patientPayload(array $overrides = []): array
    {
        return array_merge([
            'first_name' => 'Safe',
            'last_name' => 'Patient',
            'address' => 'Safe Street',
            'phone' => '555',
            'email' => null,
            'primary_dentist_id' => null,
            'notes' => null,
        ], $overrides);
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function appointmentPayload(array $overrides = []): array
    {
        return array_merge([
            'starts_at' => Carbon::tomorrow()->setTime(10, 0)->toDateTimeString(),
            'ends_at' => Carbon::tomorrow()->setTime(10, 30)->toDateTimeString(),
            'type' => Appointment::TYPE_CHECKUP,
            'assigned_user_id' => null,
            'notes' => null,
            'reminder_staff_at' => null,
            'reminder_patient_at' => null,
        ], $overrides);
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function interventionPayload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Security intervention',
            'description' => null,
            'next_step' => null,
            'intervention_date' => Carbon::today()->toDateString(),
            'appointment_id' => null,
            'performed_by_user_id' => null,
            'assigned_to_user_id' => null,
            'task_due_date' => null,
            'total_cost' => 100,
            'paid_amount' => 0,
            'reminder_staff_at' => null,
            'reminder_patient_at' => null,
        ], $overrides);
    }

    private function assertBlocked(TestResponse $response): void
    {
        $this->assertContains($response->getStatusCode(), [403, 404]);
    }

    private function assertBlockedOrValidationError(TestResponse $response): void
    {
        $status = $response->getStatusCode();

        $this->assertTrue(
            in_array($status, [302, 403, 404, 422], true),
            'Expected blocked or validation response, got status '.$status.'.'
        );
    }
}
