<?php

namespace Tests\Feature\Api;

use App\Mail\InviteMail;
use App\Models\Appointment;
use App\Models\Company;
use App\Models\Invite;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PlatformAdminApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_platform_admin_can_view_admin_dashboard(): void
    {
        $admin = $this->platformAdmin();
        [$company, $companyUser] = $this->companyWithUser();
        $patient = $this->patient($company);
        Appointment::query()->create([
            'company_id' => $company->id,
            'patient_id' => $patient->id,
            'scheduled_by_user_id' => $companyUser->id,
            'starts_at' => '2026-05-12 10:00:00',
            'type' => Appointment::TYPE_CHECKUP,
            'status' => Appointment::STATUS_SCHEDULED,
        ]);
        $this->invite($company);

        Sanctum::actingAs($admin);

        $this->getJson('/api/v1/admin/dashboard')
            ->assertOk()
            ->assertJsonPath('data.companies_total', 1)
            ->assertJsonPath('data.staff_total', 1)
            ->assertJsonPath('data.patients_total', 1)
            ->assertJsonPath('data.active_appointments_count', 1)
            ->assertJsonPath('data.pending_invites_count', 1)
            ->assertJsonStructure([
                'data' => [
                    'latest_companies',
                    'latest_invites',
                ],
            ]);
    }

    public function test_platform_admin_can_list_companies(): void
    {
        $admin = $this->platformAdmin();
        [$company] = $this->companyWithUser();
        $this->patient($company);
        $this->invite($company);

        Sanctum::actingAs($admin);

        $this->getJson('/api/v1/admin/companies')
            ->assertOk()
            ->assertJsonPath('data.0.id', $company->id)
            ->assertJsonPath('data.0.name', $company->name)
            ->assertJsonPath('data.0.staff_count', 1)
            ->assertJsonPath('data.0.patients_count', 1)
            ->assertJsonPath('data.0.pending_invites_count', 1);
    }

    public function test_platform_admin_can_view_company_detail(): void
    {
        $admin = $this->platformAdmin();
        [$company] = $this->companyWithUser();
        $company->forceFill([
            'payments_enabled' => true,
            'stripe_customer_id' => 'cus_test_admin_detail',
            'stripe_subscription_id' => 'sub_test_admin_detail',
            'subscription_status' => 'active',
            'subscription_plan' => 'monthly',
            'subscription_current_period_end' => Carbon::parse('2026-06-12 10:00:00'),
            'subscription_cancel_at_period_end' => true,
        ])->save();
        $patient = $this->patient($company);
        $invite = $this->invite($company);

        Sanctum::actingAs($admin);

        $this->getJson("/api/v1/admin/companies/{$company->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $company->id)
            ->assertJsonPath('data.kpi.staff_count', 1)
            ->assertJsonPath('data.kpi.patients_count', 1)
            ->assertJsonPath('data.kpi.pending_invites_count', 1)
            ->assertJsonPath('data.staff.0.role', User::ROLE_COMPANY_ADMIN)
            ->assertJsonPath('data.latest_patients.0.id', $patient->id)
            ->assertJsonPath('data.latest_invites.0.id', $invite->id)
            ->assertJsonPath('data.payments_enabled', true)
            ->assertJsonPath('data.subscription_status', 'active')
            ->assertJsonPath('data.subscription_plan', 'monthly')
            ->assertJsonPath('data.subscription_current_period_end', $company->subscription_current_period_end?->toIso8601String())
            ->assertJsonPath('data.current_period_end', $company->subscription_current_period_end?->toIso8601String())
            ->assertJsonPath('data.subscription_cancel_at_period_end', true)
            ->assertJsonPath('data.stripe_customer_id', 'cus_test_admin_detail')
            ->assertJsonPath('data.stripe_subscription_id', 'sub_test_admin_detail');
    }

    public function test_platform_admin_can_invite_owner(): void
    {
        Mail::fake();
        $admin = $this->platformAdmin();

        Sanctum::actingAs($admin);

        Carbon::setTestNow(Carbon::parse('2026-05-12 10:00:00'));
        $sentAt = Carbon::now();

        $this->postJson('/api/v1/admin/invite-owner', [
            'email' => 'owner@example.com',
        ])
            ->assertCreated()
            ->assertJsonPath('data.email', 'owner@example.com')
            ->assertJsonPath('data.role', User::ROLE_COMPANY_ADMIN)
            ->assertJsonPath('data.status', 'pending');

        $this->assertDatabaseHas('invites', [
            'company_id' => null,
            'email' => 'owner@example.com',
            'role' => User::ROLE_COMPANY_ADMIN,
            'accepted_at' => null,
        ]);
        $invite = Invite::query()
            ->where('email', 'owner@example.com')
            ->firstOrFail();
        $this->assertTrue($invite->expires_at->greaterThanOrEqualTo($sentAt->copy()->addMinutes(10)));
        Carbon::setTestNow();
        Mail::assertSent(InviteMail::class);
    }

    public function test_duplicate_pending_owner_invite_is_blocked(): void
    {
        Mail::fake();
        $admin = $this->platformAdmin();
        Invite::query()->create([
            'company_id' => null,
            'email' => 'owner-duplicate@example.com',
            'role' => User::ROLE_COMPANY_ADMIN,
            'token' => 'token-'.str()->random(16),
            'expires_at' => Carbon::now()->addMinutes(10),
            'metadata' => [],
        ]);

        Sanctum::actingAs($admin);

        $this->postJson('/api/v1/admin/invite-owner', [
            'email' => 'owner-duplicate@example.com',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);

        $this->assertSame(1, Invite::query()
            ->whereNull('company_id')
            ->where('email', 'owner-duplicate@example.com')
            ->where('role', User::ROLE_COMPANY_ADMIN)
            ->count());
    }

    public function test_expired_or_revoked_owner_invite_allows_new_invite(): void
    {
        Mail::fake();
        $admin = $this->platformAdmin();
        Invite::query()->create([
            'company_id' => null,
            'email' => 'expired-owner@example.com',
            'role' => User::ROLE_COMPANY_ADMIN,
            'token' => 'token-'.str()->random(16),
            'expires_at' => Carbon::now()->subMinute(),
            'metadata' => [],
        ]);
        Invite::query()->create([
            'company_id' => null,
            'email' => 'revoked-owner@example.com',
            'role' => User::ROLE_COMPANY_ADMIN,
            'token' => 'token-'.str()->random(16),
            'expires_at' => Carbon::now()->addMinutes(10),
            'revoked_at' => Carbon::now(),
            'metadata' => [],
        ]);

        Sanctum::actingAs($admin);

        $this->postJson('/api/v1/admin/invite-owner', [
            'email' => 'expired-owner@example.com',
        ])->assertCreated();

        $this->postJson('/api/v1/admin/invite-owner', [
            'email' => 'revoked-owner@example.com',
        ])->assertCreated();

        $this->assertSame(2, Invite::query()->where('email', 'expired-owner@example.com')->count());
        $this->assertSame(2, Invite::query()->where('email', 'revoked-owner@example.com')->count());
    }

    public function test_platform_admin_can_delete_company_and_its_invites(): void
    {
        $admin = $this->platformAdmin();
        [$company] = $this->companyWithUser();
        $invite = $this->invite($company);
        $this->patient($company);

        Sanctum::actingAs($admin);

        $this->deleteJson("/api/v1/admin/companies/{$company->id}")
            ->assertOk()
            ->assertJsonPath('data.deleted', true);

        $this->assertDatabaseMissing('companies', ['id' => $company->id]);
        $invite = $invite->fresh();
        $this->assertNotNull($invite);
        $this->assertNull($invite->company_id);
        $this->assertNotNull($invite->revoked_at);
    }

    public function test_admin_dashboard_staff_total_counts_only_users_in_existing_companies(): void
    {
        $platformAdmin = $this->platformAdmin();
        [$firstCompany, $firstAdmin] = $this->companyWithUser();
        [, $secondAdmin] = $this->companyWithUser();
        $dentist = User::factory()->create([
            'company_id' => $firstCompany->id,
            'role' => User::ROLE_DENTIST,
        ]);
        $nurse = User::factory()->create([
            'company_id' => $firstCompany->id,
            'role' => User::ROLE_NURSE,
        ]);

        Sanctum::actingAs($firstAdmin);

        $this->deleteJson("/api/v1/company/team/{$dentist->id}")
            ->assertOk();
        $this->deleteJson("/api/v1/company/team/{$nurse->id}")
            ->assertOk();

        Sanctum::actingAs($platformAdmin);

        $this->getJson('/api/v1/admin/dashboard')
            ->assertOk()
            ->assertJsonPath('data.staff_total', 2);

        $this->deleteJson("/api/v1/admin/companies/{$firstCompany->id}")
            ->assertOk();

        $this->getJson('/api/v1/admin/dashboard')
            ->assertOk()
            ->assertJsonPath('data.staff_total', 1);

        $this->assertDatabaseHas('users', [
            'id' => $firstAdmin->id,
            'company_id' => null,
        ]);
        $this->assertDatabaseHas('users', [
            'id' => $secondAdmin->id,
        ]);
    }

    public function test_platform_admin_gets_404_when_deleting_missing_company(): void
    {
        $admin = $this->platformAdmin();

        Sanctum::actingAs($admin);

        $this->deleteJson('/api/v1/admin/companies/999999')
            ->assertNotFound();
    }

    public function test_platform_admin_can_delete_unaccepted_invite(): void
    {
        $admin = $this->platformAdmin();
        [$company] = $this->companyWithUser();
        $invite = $this->invite($company);

        Sanctum::actingAs($admin);

        $this->deleteJson("/api/v1/admin/invites/{$invite->id}")
            ->assertOk()
            ->assertJsonPath('data.deleted', true);

        $this->assertNotNull($invite->fresh()->revoked_at);
    }

    public function test_platform_admin_cannot_delete_accepted_invite(): void
    {
        $admin = $this->platformAdmin();
        [$company, $companyUser] = $this->companyWithUser();
        $invite = $this->invite($company);
        $invite->forceFill([
            'accepted_at' => Carbon::now(),
            'accepted_by_user_id' => $companyUser->id,
        ])->save();

        Sanctum::actingAs($admin);

        $this->deleteJson("/api/v1/admin/invites/{$invite->id}")
            ->assertForbidden()
            ->assertJsonPath('message', 'Prihvacene pozivnice ne mogu da se opozovu.');

        $this->assertDatabaseHas('invites', ['id' => $invite->id]);
    }

    public function test_platform_admin_can_resend_pending_or_expired_invite(): void
    {
        Mail::fake();
        $admin = $this->platformAdmin();
        [$company] = $this->companyWithUser();
        $pendingInvite = $this->invite($company);
        $expiredInvite = $this->invite($company);
        $expiredInvite->forceFill([
            'email' => 'expired-admin-resend@example.com',
            'expires_at' => Carbon::now()->subDay(),
        ])->save();
        $oldPendingToken = $pendingInvite->token;
        $oldExpiredToken = $expiredInvite->token;

        Sanctum::actingAs($admin);

        $this->postJson("/api/v1/admin/invites/{$pendingInvite->id}/resend")
            ->assertOk()
            ->assertJsonPath('data.id', $pendingInvite->id);

        $this->postJson("/api/v1/admin/invites/{$expiredInvite->id}/resend")
            ->assertOk()
            ->assertJsonPath('data.id', $expiredInvite->id);

        $this->assertNotSame($oldPendingToken, $pendingInvite->fresh()->token);
        $this->assertNotSame($oldExpiredToken, $expiredInvite->fresh()->token);
        $this->assertTrue($pendingInvite->fresh()->expires_at->greaterThan(Carbon::now()));
        $this->assertTrue($expiredInvite->fresh()->expires_at->greaterThan(Carbon::now()));
        Mail::assertSent(InviteMail::class, 2);
    }

    public function test_platform_admin_cannot_resend_accepted_or_revoked_invite(): void
    {
        $admin = $this->platformAdmin();
        [$company, $companyUser] = $this->companyWithUser();
        $acceptedInvite = $this->invite($company);
        $acceptedInvite->forceFill([
            'accepted_at' => Carbon::now(),
            'accepted_by_user_id' => $companyUser->id,
        ])->save();
        $revokedInvite = $this->invite($company);
        $revokedInvite->forceFill([
            'email' => 'revoked-admin-resend@example.com',
            'revoked_at' => Carbon::now(),
        ])->save();

        Sanctum::actingAs($admin);

        $this->postJson("/api/v1/admin/invites/{$acceptedInvite->id}/resend")
            ->assertUnprocessable();

        $this->postJson("/api/v1/admin/invites/{$revokedInvite->id}/resend")
            ->assertUnprocessable();
    }

    public function test_company_admin_cannot_access_admin_dashboard(): void
    {
        [, $companyAdmin] = $this->companyWithUser();

        Sanctum::actingAs($companyAdmin);

        $this->getJson('/api/v1/admin/dashboard')
            ->assertForbidden()
            ->assertHeader('content-type', 'application/json')
            ->assertJsonMissingPath('data');
    }

    public function test_company_admin_cannot_access_admin_company_detail(): void
    {
        [$company, $companyAdmin] = $this->companyWithUser();

        Sanctum::actingAs($companyAdmin);

        $this->getJson("/api/v1/admin/companies/{$company->id}")
            ->assertForbidden()
            ->assertHeader('content-type', 'application/json')
            ->assertJsonMissingPath('data');
    }

    public function test_unauthenticated_user_gets_401(): void
    {
        $this->getJson('/api/v1/admin/dashboard')
            ->assertUnauthorized()
            ->assertHeader('content-type', 'application/json')
            ->assertJsonMissingPath('data');
    }

    private function platformAdmin(): User
    {
        return User::factory()->create([
            'company_id' => null,
            'name' => 'Platform Admin',
            'first_name' => 'Platform',
            'last_name' => 'Admin',
            'role' => User::ROLE_PLATFORM_ADMIN,
        ]);
    }

    /**
     * @return array{Company, User}
     */
    private function companyWithUser(): array
    {
        $company = Company::query()->create([
            'name' => 'Test Ordinacija',
            'address' => 'Test adresa',
            'email' => 'office@example.com',
            'phone' => '060123456',
        ]);

        $user = User::factory()->create([
            'company_id' => $company->id,
            'name' => 'Company Admin',
            'first_name' => 'Company',
            'last_name' => 'Admin',
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
            'address' => 'Adresa',
            'phone' => '060111222',
            'manual_status' => Patient::STATUS_ACTIVE,
        ]);
    }

    private function invite(Company $company): Invite
    {
        return Invite::query()->create([
            'company_id' => $company->id,
            'email' => 'staff@example.com',
            'role' => User::ROLE_DENTIST,
            'token' => 'token-'.str()->random(16),
            'expires_at' => Carbon::now()->addWeek(),
            'metadata' => [],
        ]);
    }
}
