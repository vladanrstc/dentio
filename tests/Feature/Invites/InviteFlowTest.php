<?php

namespace Tests\Feature\Invites;

use App\Mail\InviteMail;
use App\Models\Company;
use App\Models\Invite;
use App\Models\Patient;
use App\Models\PatientTask;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class InviteFlowTest extends TestCase
{
    use RefreshDatabase;

    private const CSRF_TOKEN = 'test-token';

    public function test_platform_admin_can_access_admin_dashboard(): void
    {
        $platformAdmin = User::factory()->platformAdmin()->create();

        $this->actingAs($platformAdmin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Platform Admin');
    }

    public function test_platform_admin_can_send_company_owner_invite(): void
    {
        Mail::fake();
        $platformAdmin = User::factory()->platformAdmin()->create();

        $this->actingAs($platformAdmin)
            ->withSession(['_token' => self::CSRF_TOKEN])
            ->post(route('admin.invites.company-owner.store'), $this->formData([
                'email' => 'owner@example.com',
            ]))
            ->assertRedirect(route('admin.dashboard'));

        $this->assertDatabaseHas('invites', [
            'company_id' => null,
            'email' => 'owner@example.com',
            'role' => User::ROLE_COMPANY_ADMIN,
            'invited_by_user_id' => null,
            'accepted_by_user_id' => null,
            'accepted_at' => null,
        ]);
        Mail::assertSent(InviteMail::class, 1);
        Mail::assertSent(InviteMail::class, fn (InviteMail $mail) => $mail->invite->email === 'owner@example.com');
        Mail::assertNothingQueued();
    }

    public function test_non_platform_admin_cannot_send_company_owner_invite(): void
    {
        Mail::fake();
        $companyAdmin = User::factory()->companyAdmin()->create();

        $this->actingAs($companyAdmin)
            ->withSession(['_token' => self::CSRF_TOKEN])
            ->post(route('admin.invites.company-owner.store'), $this->formData([
                'email' => 'owner@example.com',
            ]))
            ->assertForbidden();

        $this->assertDatabaseMissing('invites', [
            'email' => 'owner@example.com',
        ]);
        Mail::assertNothingSent();
    }

    public function test_company_admin_can_access_team_invites_page(): void
    {
        $companyAdmin = User::factory()->companyAdmin()->create();

        $this->actingAs($companyAdmin)
            ->get(route('team.invites.index'))
            ->assertOk()
            ->assertSee('Posalji invite');
    }

    public function test_company_admin_can_send_staff_invite_for_dentist(): void
    {
        Mail::fake();
        $company = Company::factory()->create();
        $companyAdmin = User::factory()->companyAdmin()->forCompany($company)->create();

        $this->actingAs($companyAdmin)
            ->withSession(['_token' => self::CSRF_TOKEN])
            ->post(route('team.invites.store'), $this->formData([
                'email' => 'dentist@example.com',
                'role' => User::ROLE_DENTIST,
            ]))
            ->assertRedirect();

        $this->assertDatabaseHas('invites', [
            'company_id' => $company->id,
            'email' => 'dentist@example.com',
            'role' => User::ROLE_DENTIST,
            'invited_by_user_id' => $companyAdmin->id,
            'accepted_by_user_id' => null,
            'accepted_at' => null,
        ]);
        Mail::assertSent(InviteMail::class, 1);
        Mail::assertSent(InviteMail::class, fn (InviteMail $mail) => $mail->invite->email === 'dentist@example.com');
        Mail::assertNothingQueued();
    }

    public function test_company_admin_can_send_staff_invite_for_nurse(): void
    {
        Mail::fake();
        $company = Company::factory()->create();
        $companyAdmin = User::factory()->companyAdmin()->forCompany($company)->create();

        $this->actingAs($companyAdmin)
            ->withSession(['_token' => self::CSRF_TOKEN])
            ->post(route('team.invites.store'), $this->formData([
                'email' => 'nurse@example.com',
                'role' => User::ROLE_NURSE,
            ]))
            ->assertRedirect();

        $this->assertDatabaseHas('invites', [
            'company_id' => $company->id,
            'email' => 'nurse@example.com',
            'role' => User::ROLE_NURSE,
            'invited_by_user_id' => $companyAdmin->id,
        ]);
        Mail::assertSent(InviteMail::class, 1);
        Mail::assertSent(InviteMail::class, fn (InviteMail $mail) => $mail->invite->email === 'nurse@example.com');
        Mail::assertNothingQueued();
    }

    public function test_api_company_staff_invite_uses_authenticated_users_company(): void
    {
        Mail::fake();
        $company = Company::factory()->create();
        $otherCompany = Company::factory()->create();
        $companyAdmin = User::factory()->companyAdmin()->forCompany($company)->create();

        $response = $this->actingAs($companyAdmin, 'sanctum')
            ->postJson('/api/v1/company/invites', [
                'email' => 'api-dentist@example.com',
                'role' => User::ROLE_DENTIST,
            ]);

        $response->assertCreated();
        $this->assertDatabaseHas('invites', [
            'company_id' => $company->id,
            'email' => 'api-dentist@example.com',
            'role' => User::ROLE_DENTIST,
            'invited_by_user_id' => $companyAdmin->id,
        ]);
        $this->assertDatabaseMissing('invites', [
            'company_id' => $otherCompany->id,
            'email' => 'api-dentist@example.com',
        ]);
    }

    public function test_api_company_staff_invite_rejects_company_fields(): void
    {
        Mail::fake();
        $company = Company::factory()->create();
        $otherCompany = Company::factory()->create();
        $companyAdmin = User::factory()->companyAdmin()->forCompany($company)->create();

        $response = $this->actingAs($companyAdmin, 'sanctum')
            ->postJson('/api/v1/company/invites', [
                'email' => 'cross-company@example.com',
                'role' => User::ROLE_DENTIST,
                'company_id' => $otherCompany->id,
                'company_name' => 'Other Clinic',
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['company_id', 'company_name']);
        $this->assertDatabaseMissing('invites', [
            'email' => 'cross-company@example.com',
        ]);
        Mail::assertNothingQueued();
    }

    public function test_api_company_staff_invite_rejects_non_worker_roles(): void
    {
        Mail::fake();
        $companyAdmin = User::factory()->companyAdmin()->create();

        $this->actingAs($companyAdmin, 'sanctum')
            ->postJson('/api/v1/company/invites', [
                'email' => 'owner-role@example.com',
                'role' => User::ROLE_COMPANY_ADMIN,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['role']);

        $this->assertDatabaseMissing('invites', [
            'email' => 'owner-role@example.com',
        ]);
        Mail::assertNothingQueued();
    }

    public function test_api_platform_admin_can_send_company_owner_invite(): void
    {
        Mail::fake();
        $platformAdmin = User::factory()->platformAdmin()->create();

        $response = $this->actingAs($platformAdmin, 'sanctum')
            ->postJson('/api/v1/admin/invites/company-owner', [
                'email' => 'api-owner@example.com',
            ]);

        $response->assertCreated();
        $this->assertDatabaseHas('invites', [
            'company_id' => null,
            'email' => 'api-owner@example.com',
            'role' => User::ROLE_COMPANY_ADMIN,
            'invited_by_user_id' => null,
        ]);
    }

    public function test_api_company_admin_cannot_send_company_owner_invite(): void
    {
        Mail::fake();
        $companyAdmin = User::factory()->companyAdmin()->create();

        $this->actingAs($companyAdmin, 'sanctum')
            ->postJson('/api/v1/admin/invites/company-owner', [
                'email' => 'forbidden-owner@example.com',
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('invites', [
            'email' => 'forbidden-owner@example.com',
        ]);
        Mail::assertNothingQueued();
    }

    public function test_dentist_and_nurse_cannot_access_team_invite_page(): void
    {
        $company = Company::factory()->create();
        $dentist = User::factory()->dentist()->forCompany($company)->create();
        $nurse = User::factory()->nurse()->forCompany($company)->create();

        $this->actingAs($dentist)
            ->get(route('team.invites.index'))
            ->assertForbidden();

        $this->actingAs($nurse)
            ->get(route('team.invites.index'))
            ->assertForbidden();
    }

    public function test_invite_accept_page_renders_for_valid_token(): void
    {
        $invite = Invite::factory()->create([
            'email' => 'staff@example.com',
            'role' => User::ROLE_DENTIST,
            'expires_at' => now()->addDay(),
            'accepted_at' => null,
        ]);

        $this->get(route('invites.accept.show', $invite->token))
            ->assertOk()
            ->assertSee('Aktivacija naloga')
            ->assertSee('staff@example.com');
    }

    public function test_accepting_owner_invite_creates_company_and_company_admin_user(): void
    {
        Mail::fake();
        $invite = Invite::factory()->create([
            'company_id' => null,
            'email' => 'owner@example.com',
            'role' => User::ROLE_COMPANY_ADMIN,
            'expires_at' => now()->addDay(),
            'accepted_at' => null,
        ]);

        $this->withSession(['_token' => self::CSRF_TOKEN])
            ->post(route('invites.accept.store', $invite->token), $this->formData([
                'first_name' => 'Owner',
                'last_name' => 'User',
                'phone' => '060111222',
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'requires_company' => '1',
                'company_name' => 'Owner Dental',
                'company_address' => 'Owner Street 1',
                'company_phone' => '011123456',
            ]))
            ->assertRedirect(route('dashboard.index'));

        $company = Company::query()->where('name', 'Owner Dental')->firstOrFail();
        $user = User::query()->where('email', 'owner@example.com')->firstOrFail();

        $this->assertSame($company->id, $user->company_id);
        $this->assertSame(User::ROLE_COMPANY_ADMIN, $user->role);
        $this->assertSame($user->id, $company->fresh()->created_by_user_id);
        $this->assertDatabaseHas('invites', [
            'id' => $invite->id,
            'accepted_by_user_id' => $user->id,
        ]);
        $this->assertNotNull($invite->fresh()->accepted_at);
    }

    public function test_accepting_staff_invite_creates_user_in_invited_company(): void
    {
        Mail::fake();
        $company = Company::factory()->create();
        $invite = Invite::factory()->create([
            'company_id' => $company->id,
            'email' => 'staff@example.com',
            'role' => User::ROLE_NURSE,
            'expires_at' => now()->addDay(),
            'accepted_at' => null,
        ]);

        $this->withSession(['_token' => self::CSRF_TOKEN])
            ->post(route('invites.accept.store', $invite->token), $this->formData([
                'first_name' => 'Staff',
                'last_name' => 'User',
                'phone' => '060333444',
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'requires_company' => '0',
            ]))
            ->assertRedirect(route('dashboard.index'));

        $user = User::query()->where('email', 'staff@example.com')->firstOrFail();

        $this->assertSame($company->id, $user->company_id);
        $this->assertSame(User::ROLE_NURSE, $user->role);
        $this->assertDatabaseHas('invites', [
            'id' => $invite->id,
            'accepted_by_user_id' => $user->id,
        ]);
        $this->assertNotNull($invite->fresh()->accepted_at);
    }

    public function test_api_accept_invite_returns_success_message(): void
    {
        $company = Company::factory()->create();
        $invite = Invite::factory()->create([
            'company_id' => $company->id,
            'email' => 'api-staff-accept@example.com',
            'role' => User::ROLE_DENTIST,
            'expires_at' => now()->addDay(),
            'accepted_at' => null,
        ]);

        $this->postJson('/api/v1/invites/'.$invite->token.'/accept', [
            'first_name' => 'Api',
            'last_name' => 'Staff',
            'phone' => '060555666',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])
            ->assertCreated()
            ->assertJson([
                'message' => 'Invite accepted successfully.',
            ]);

        $this->assertDatabaseHas('users', [
            'email' => 'api-staff-accept@example.com',
            'company_id' => $company->id,
            'role' => User::ROLE_DENTIST,
        ]);
        $this->assertNotNull($invite->fresh()->accepted_at);
    }

    public function test_api_accept_invite_returns_clear_message_for_used_invite(): void
    {
        $invite = Invite::factory()->create([
            'accepted_at' => now(),
            'accepted_by_user_id' => User::factory()->create()->id,
            'expires_at' => now()->addDay(),
        ]);

        $this->postJson('/api/v1/invites/'.$invite->token.'/accept', [
            'first_name' => 'Used',
            'last_name' => 'Invite',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])
            ->assertGone()
            ->assertJson([
                'message' => 'Pozivnica nije validna ili je istekla.',
            ]);
    }

    public function test_company_user_can_invite_patient_to_portal_and_accept_links_patient(): void
    {
        Mail::fake();
        $company = Company::factory()->create();
        $companyAdmin = User::factory()->companyAdmin()->forCompany($company)->create();
        $patient = Patient::factory()->create([
            'company_id' => $company->id,
            'email' => 'patient@example.com',
            'user_id' => null,
        ]);

        $response = $this->actingAs($companyAdmin, 'sanctum')
            ->postJson('/api/v1/patients/'.$patient->id.'/portal-invite');

        $response->assertCreated()
            ->assertJsonPath('data.email', 'patient@example.com')
            ->assertJsonPath('data.role', User::ROLE_PATIENT)
            ->assertJsonPath('data.metadata.patient_id', $patient->id);

        $invite = Invite::query()->where('email', 'patient@example.com')->firstOrFail();

        $this->postJson('/api/v1/invites/'.$invite->token.'/accept', [
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertCreated();

        $user = User::query()->where('email', 'patient@example.com')->firstOrFail();

        $this->assertSame(User::ROLE_PATIENT, $user->role);
        $this->assertSame($company->id, $user->company_id);
        $this->assertSame($user->id, $patient->fresh()->user_id);
        $this->assertNotNull($invite->fresh()->accepted_at);
        Mail::assertSent(InviteMail::class, 1);
    }

    public function test_api_patient_invite_accept_does_not_require_profile_fields(): void
    {
        $company = Company::factory()->create();
        $patient = Patient::factory()->create([
            'company_id' => $company->id,
            'first_name' => 'Portal',
            'last_name' => 'Patient',
            'email' => 'portal-patient@example.com',
            'phone' => '060123123',
            'user_id' => null,
        ]);
        $invite = Invite::factory()->create([
            'company_id' => $company->id,
            'email' => 'portal-patient@example.com',
            'role' => User::ROLE_PATIENT,
            'expires_at' => now()->addDay(),
            'accepted_at' => null,
            'metadata' => ['patient_id' => $patient->id],
        ]);

        $this->postJson('/api/v1/invites/'.$invite->token.'/accept', [
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertCreated();

        $user = User::query()->where('email', 'portal-patient@example.com')->firstOrFail();

        $this->assertSame('Portal', $user->first_name);
        $this->assertSame('Patient', $user->last_name);
        $this->assertSame('060123123', $user->phone);
        $this->assertSame($user->id, $patient->fresh()->user_id);
    }

    public function test_api_patient_invite_returns_clear_error_when_patient_already_has_user(): void
    {
        $company = Company::factory()->create();
        $linkedUser = User::factory()->forCompany($company)->create([
            'role' => User::ROLE_PATIENT,
        ]);
        $patient = Patient::factory()->create([
            'company_id' => $company->id,
            'email' => 'already-linked@example.com',
            'user_id' => $linkedUser->id,
        ]);
        $invite = Invite::factory()->create([
            'company_id' => $company->id,
            'email' => 'already-linked@example.com',
            'role' => User::ROLE_PATIENT,
            'expires_at' => now()->addDay(),
            'accepted_at' => null,
            'metadata' => ['patient_id' => $patient->id],
        ]);

        $this->postJson('/api/v1/invites/'.$invite->token.'/accept', [
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['invite'])
            ->assertJsonPath('errors.invite.0', 'Pacijent vec ima povezan portal nalog.');

        $this->assertNull($invite->fresh()->accepted_at);
    }

    public function test_patient_portal_uses_authenticated_patient_link(): void
    {
        $company = Company::factory()->create();
        $patientUser = User::factory()->forCompany($company)->create([
            'role' => User::ROLE_PATIENT,
            'email' => 'linked-patient@example.com',
        ]);
        $patient = Patient::factory()->create([
            'company_id' => $company->id,
            'user_id' => $patientUser->id,
            'email' => 'linked-patient@example.com',
        ]);
        $otherPatient = Patient::factory()->create([
            'company_id' => $company->id,
            'email' => 'other-patient@example.com',
        ]);

        $response = $this->actingAs($patientUser, 'sanctum')
            ->getJson('/api/v1/patient-portal/me');

        $response->assertOk()
            ->assertJsonPath('data.patient.id', $patient->id)
            ->assertJsonMissing(['id' => $otherPatient->id])
            ->assertJsonMissing(['email' => 'other-patient@example.com']);
    }

    public function test_patient_portal_includes_open_and_completed_tasks_for_authenticated_patient(): void
    {
        $company = Company::factory()->create();
        $patientUser = User::factory()->forCompany($company)->create([
            'role' => User::ROLE_PATIENT,
            'email' => 'task-patient@example.com',
        ]);
        $staffUser = User::factory()->forCompany($company)->create();
        $patient = Patient::factory()->create([
            'company_id' => $company->id,
            'user_id' => $patientUser->id,
            'email' => 'task-patient@example.com',
        ]);
        $otherPatient = Patient::factory()->create([
            'company_id' => $company->id,
        ]);

        $openTask = PatientTask::factory()->create([
            'company_id' => $company->id,
            'patient_id' => $patient->id,
            'created_by_user_id' => $staffUser->id,
            'assigned_to_user_id' => $staffUser->id,
            'description' => 'Doneti snimak na sledeci pregled.',
            'status' => PatientTask::STATUS_OPEN,
        ]);
        $completedTask = PatientTask::factory()->create([
            'company_id' => $company->id,
            'patient_id' => $patient->id,
            'created_by_user_id' => $staffUser->id,
            'closed_by_user_id' => $staffUser->id,
            'description' => 'Popunjena anamneza.',
            'status' => PatientTask::STATUS_DONE,
            'closed_at' => now(),
        ]);
        PatientTask::factory()->create([
            'company_id' => $company->id,
            'patient_id' => $otherPatient->id,
            'description' => 'Tudji zadatak.',
            'status' => PatientTask::STATUS_OPEN,
        ]);

        $response = $this->actingAs($patientUser, 'sanctum')
            ->getJson('/api/v1/patient-portal/me');

        $response->assertOk()
            ->assertJsonPath('data.open_tasks.0.id', $openTask->id)
            ->assertJsonPath('data.open_tasks.0.description', 'Doneti snimak na sledeci pregled.')
            ->assertJsonPath('data.completed_tasks.0.id', $completedTask->id)
            ->assertJsonPath('data.completed_tasks.0.description', 'Popunjena anamneza.')
            ->assertJsonMissing(['description' => 'Tudji zadatak.']);
    }

    public function test_staff_cannot_access_patient_portal_endpoint(): void
    {
        $companyAdmin = User::factory()->companyAdmin()->create();

        $this->actingAs($companyAdmin, 'sanctum')
            ->getJson('/api/v1/patient-portal/me')
            ->assertForbidden();
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
