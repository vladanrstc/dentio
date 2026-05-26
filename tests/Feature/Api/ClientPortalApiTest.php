<?php

namespace Tests\Feature\Api;

use App\Mail\PatientPortalInviteMail;
use App\Models\Appointment;
use App\Models\Company;
use App\Models\Intervention;
use App\Models\Patient;
use App\Models\PatientPortalInvite;
use App\Models\PatientTask;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ClientPortalApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_patient_can_login_and_view_own_dashboard(): void
    {
        [$company, $doctor] = $this->companyWithDoctor();
        $patient = $this->patient($company, [
            'email' => 'patient@example.com',
            'password' => 'secret-password',
        ]);

        Appointment::query()->create([
            'company_id' => $company->id,
            'patient_id' => $patient->id,
            'assigned_user_id' => $doctor->id,
            'starts_at' => '2026-05-20 10:00:00',
            'ends_at' => '2026-05-20 10:30:00',
            'type' => Appointment::TYPE_CHECKUP,
            'status' => Appointment::STATUS_SCHEDULED,
            'notes' => 'Client portal kontrola',
        ]);
        Intervention::query()->create([
            'company_id' => $company->id,
            'patient_id' => $patient->id,
            'performed_by_user_id' => $doctor->id,
            'title' => 'Client portal intervencija',
            'intervention_date' => '2026-05-19',
            'total_cost' => 7000,
            'paid_amount' => 3000,
        ]);
        PatientTask::query()->create([
            'company_id' => $company->id,
            'patient_id' => $patient->id,
            'assigned_to_user_id' => $doctor->id,
            'description' => 'Client portal sledeci korak',
            'status' => PatientTask::STATUS_OPEN,
        ]);

        $token = $this->postJson('/api/v1/login', [
            'email' => 'patient@example.com',
            'password' => 'secret-password',
        ])
            ->assertOk()
            ->assertJsonPath('user.id', $patient->id)
            ->assertJsonPath('user.email', 'patient@example.com')
            ->assertJsonPath('user.role', Patient::ROLE_CLIENT)
            ->assertJsonStructure(['token', 'user'])
            ->json('token');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/client/dashboard')
            ->assertOk()
            ->assertJsonPath('data.patient.id', $patient->id)
            ->assertJsonPath('data.financials.total_cost', 7000)
            ->assertJsonPath('data.financials.paid_amount', 3000)
            ->assertJsonPath('data.financials.outstanding_amount', 4000)
            ->assertJsonFragment(['notes' => 'Client portal kontrola'])
            ->assertJsonFragment(['title' => 'Client portal intervencija'])
            ->assertJsonFragment(['description' => 'Client portal sledeci korak']);
    }

    public function test_patient_can_only_see_own_data(): void
    {
        [$company, $doctor] = $this->companyWithDoctor();
        $patient = $this->patient($company, [
            'email' => 'own@example.com',
            'password' => 'secret-password',
        ]);
        $otherPatient = $this->patient($company, [
            'email' => 'other@example.com',
            'first_name' => 'Other',
            'last_name' => 'Patient',
        ]);

        Appointment::query()->create([
            'company_id' => $company->id,
            'patient_id' => $patient->id,
            'assigned_user_id' => $doctor->id,
            'starts_at' => '2026-05-20 10:00:00',
            'type' => Appointment::TYPE_CHECKUP,
            'notes' => 'Vidljivo samo mom pacijentu',
        ]);
        Appointment::query()->create([
            'company_id' => $company->id,
            'patient_id' => $otherPatient->id,
            'assigned_user_id' => $doctor->id,
            'starts_at' => '2026-05-20 11:00:00',
            'type' => Appointment::TYPE_CHECKUP,
            'notes' => 'Tudji termin ne sme da se vidi',
        ]);
        Intervention::query()->create([
            'company_id' => $company->id,
            'patient_id' => $otherPatient->id,
            'performed_by_user_id' => $doctor->id,
            'title' => 'Tudja intervencija',
            'intervention_date' => '2026-05-19',
        ]);
        PatientTask::query()->create([
            'company_id' => $company->id,
            'patient_id' => $otherPatient->id,
            'description' => 'Tudji zadatak',
            'status' => PatientTask::STATUS_OPEN,
        ]);

        $token = $this->clientToken('own@example.com', 'secret-password');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/client/appointments')
            ->assertOk()
            ->assertJsonFragment(['notes' => 'Vidljivo samo mom pacijentu'])
            ->assertJsonMissing(['notes' => 'Tudji termin ne sme da se vidi']);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/client/interventions')
            ->assertOk()
            ->assertJsonMissing(['title' => 'Tudja intervencija']);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/client/tasks')
            ->assertOk()
            ->assertJsonMissing(['description' => 'Tudji zadatak']);
    }

    public function test_company_user_token_cannot_use_client_endpoint(): void
    {
        [, $doctor] = $this->companyWithDoctor();

        Sanctum::actingAs($doctor);

        $this->getJson('/api/v1/client/me')
            ->assertForbidden()
            ->assertHeader('content-type', 'application/json');
    }

    public function test_client_token_cannot_use_company_or_admin_endpoints(): void
    {
        [$company] = $this->companyWithDoctor();
        $patient = $this->patient($company, [
            'email' => 'client@example.com',
            'password' => 'secret-password',
        ]);

        Sanctum::actingAs($patient);

        $this->getJson('/api/v1/company/patients')
            ->assertForbidden()
            ->assertHeader('content-type', 'application/json');

        $this->getJson('/api/v1/admin/dashboard')
            ->assertForbidden()
            ->assertHeader('content-type', 'application/json');

        $this->getJson('/api/v1/dashboard')
            ->assertForbidden()
            ->assertHeader('content-type', 'application/json');
    }

    public function test_client_login_rejects_invalid_credentials(): void
    {
        [$company] = $this->companyWithDoctor();
        $this->patient($company, [
            'email' => 'patient@example.com',
            'password' => 'secret-password',
        ]);

        $this->postJson('/api/v1/login', [
            'email' => 'patient@example.com',
            'password' => 'wrong-password',
        ])
            ->assertUnprocessable()
            ->assertHeader('content-type', 'application/json')
            ->assertJsonPath('errors.email.0', __('errors.invalid_credentials'));
    }

    public function test_unauthenticated_client_request_returns_401(): void
    {
        $this->getJson('/api/v1/client/me')
            ->assertUnauthorized()
            ->assertHeader('content-type', 'application/json');
    }

    public function test_shared_logout_works_for_client(): void
    {
        [$company] = $this->companyWithDoctor();
        $this->patient($company, [
            'email' => 'patient@example.com',
            'password' => 'secret-password',
        ]);

        $token = $this->clientToken('patient@example.com', 'secret-password');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/logout')
            ->assertOk()
            ->assertJsonPath('message', __('auth.logged_out'));

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_company_user_can_send_portal_invite_to_patient_from_own_company(): void
    {
        Mail::fake();
        [$company, $doctor] = $this->companyWithDoctor();
        $patient = $this->patient($company, [
            'email' => 'patient@example.com',
        ]);

        Sanctum::actingAs($doctor);

        $this->postJson('/api/v1/company/patients/portal-invites', [
            'email' => 'patient@example.com',
        ])
            ->assertCreated()
            ->assertJsonPath('data.email', 'patient@example.com')
            ->assertJsonPath('data.valid', true)
            ->assertJsonMissingPath('data.token');

        $this->assertDatabaseHas('patient_portal_invites', [
            'company_id' => $company->id,
            'patient_id' => $patient->id,
            'email' => 'patient@example.com',
            'accepted_at' => null,
        ]);

        Mail::assertSent(PatientPortalInviteMail::class, function (PatientPortalInviteMail $mail): bool {
            return $mail->hasTo('patient@example.com')
                && $mail->token !== ''
                && str_contains($mail->render(), '/client/setup-password?token=');
        });
    }

    public function test_company_user_cannot_send_portal_invite_to_patient_from_other_company(): void
    {
        Mail::fake();
        [, $doctor] = $this->companyWithDoctor();
        [$otherCompany] = $this->companyWithDoctor('other');
        $this->patient($otherCompany, [
            'email' => 'other-patient@example.com',
        ]);

        Sanctum::actingAs($doctor);

        $this->postJson('/api/v1/company/patients/portal-invites', [
            'email' => 'other-patient@example.com',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('errors.email.0', __('errors.patient_portal_patient_not_found'));

        Mail::assertNothingSent();
    }

    public function test_portal_invite_for_unknown_email_returns_validation_error(): void
    {
        Mail::fake();
        [, $doctor] = $this->companyWithDoctor();

        Sanctum::actingAs($doctor);

        $this->postJson('/api/v1/company/patients/portal-invites', [
            'email' => 'missing@example.com',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('errors.email.0', __('errors.patient_portal_patient_not_found'));

        Mail::assertNothingSent();
    }

    public function test_client_can_accept_valid_portal_invite_and_set_password(): void
    {
        $token = 'valid-client-token';
        [$company] = $this->companyWithDoctor();
        $patient = $this->patient($company, [
            'email' => 'patient@example.com',
            'password' => null,
        ]);
        $invite = $this->portalInvite($patient, $token);

        $this->getJson("/api/v1/client/invites/{$token}")
            ->assertOk()
            ->assertJsonPath('data.email', 'patient@example.com')
            ->assertJsonPath('data.valid', true)
            ->assertJsonPath('data.expired', false)
            ->assertJsonPath('data.accepted', false);

        $this->postJson("/api/v1/client/invites/{$token}/accept", [
            'password' => 'new-client-password',
            'password_confirmation' => 'new-client-password',
        ])
            ->assertOk()
            ->assertJsonPath('data.patient.id', $patient->id)
            ->assertJsonPath('data.patient.email', 'patient@example.com');

        $this->assertNotNull($invite->fresh()->accepted_at);

        $loginToken = $this->clientToken('patient@example.com', 'new-client-password');
        $this->assertNotSame('', $loginToken);
    }

    public function test_expired_or_invalid_portal_invite_token_does_not_work(): void
    {
        $token = 'expired-client-token';
        [$company] = $this->companyWithDoctor();
        $patient = $this->patient($company, [
            'email' => 'patient@example.com',
        ]);
        $this->portalInvite($patient, $token, [
            'expires_at' => Carbon::now()->subMinute(),
        ]);

        $this->postJson("/api/v1/client/invites/{$token}/accept", [
            'password' => 'new-client-password',
            'password_confirmation' => 'new-client-password',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('errors.token.0', __('errors.invite_expired'));

        $this->getJson('/api/v1/client/invites/not-a-real-token')
            ->assertNotFound()
            ->assertHeader('content-type', 'application/json');
    }

    public function test_portal_invite_token_cannot_be_reused_after_accept(): void
    {
        $token = 'single-use-client-token';
        [$company] = $this->companyWithDoctor();
        $patient = $this->patient($company, [
            'email' => 'patient@example.com',
        ]);
        $this->portalInvite($patient, $token);

        $this->postJson("/api/v1/client/invites/{$token}/accept", [
            'password' => 'new-client-password',
            'password_confirmation' => 'new-client-password',
        ])->assertOk();

        $this->postJson("/api/v1/client/invites/{$token}/accept", [
            'password' => 'another-password',
            'password_confirmation' => 'another-password',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('errors.token.0', __('errors.invite_already_accepted'));
    }

    /**
     * @return array{Company, User}
     */
    private function companyWithDoctor(string $suffix = 'main'): array
    {
        $company = Company::query()->create([
            'name' => "Dentio Test {$suffix}",
            'address' => 'Test adresa',
            'email' => "office-{$suffix}@example.com",
            'phone' => '060123456',
        ]);

        $doctor = User::factory()->create([
            'company_id' => $company->id,
            'first_name' => 'Dr',
            'last_name' => 'Dentio',
            'role' => User::ROLE_DENTIST,
        ]);

        return [$company, $doctor];
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function patient(Company $company, array $overrides = []): Patient
    {
        return Patient::query()->create(array_merge([
            'company_id' => $company->id,
            'first_name' => 'Petar',
            'last_name' => 'Petrovic',
            'phone' => '060111222',
            'email' => 'petar@example.com',
            'manual_status' => Patient::STATUS_ACTIVE,
        ], $overrides));
    }

    private function clientToken(string $email, string $password): string
    {
        return (string) $this->postJson('/api/v1/login', [
            'email' => $email,
            'password' => $password,
        ])
            ->assertOk()
            ->assertJsonPath('user.role', Patient::ROLE_CLIENT)
            ->json('token');
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function portalInvite(Patient $patient, string $token, array $overrides = []): PatientPortalInvite
    {
        return PatientPortalInvite::query()->create(array_merge([
            'company_id' => $patient->company_id,
            'patient_id' => $patient->id,
            'email' => $patient->email,
            'token_hash' => hash('sha256', $token),
            'expires_at' => Carbon::now()->addMinutes(10),
        ], $overrides));
    }
}
