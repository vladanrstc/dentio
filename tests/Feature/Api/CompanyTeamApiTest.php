<?php

namespace Tests\Feature\Api;

use App\Mail\InviteMail;
use App\Models\Company;
use App\Models\Invite;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CompanyTeamApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_admin_can_list_team_members(): void
    {
        [$company, $admin] = $this->companyUser(User::ROLE_COMPANY_ADMIN);
        $dentist = $this->user($company, User::ROLE_DENTIST, 'dentist');
        $nurse = $this->user($company, User::ROLE_NURSE, 'nurse');

        Sanctum::actingAs($admin);

        $this->getJson('/api/v1/company/team')
            ->assertOk()
            ->assertJsonFragment(['id' => $admin->id, 'role' => User::ROLE_COMPANY_ADMIN])
            ->assertJsonFragment(['id' => $dentist->id, 'role' => User::ROLE_DENTIST])
            ->assertJsonFragment(['id' => $nurse->id, 'role' => User::ROLE_NURSE])
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'first_name',
                        'last_name',
                        'full_name',
                        'email',
                        'phone',
                        'role',
                        'created_at',
                    ],
                ],
            ]);
    }

    public function test_company_admin_can_list_company_invites(): void
    {
        [$company, $admin] = $this->companyUser(User::ROLE_COMPANY_ADMIN);
        $invite = $this->invite($company, User::ROLE_DENTIST);

        Sanctum::actingAs($admin);

        $this->getJson('/api/v1/company/invites')
            ->assertOk()
            ->assertJsonPath('data.0.id', $invite->id)
            ->assertJsonPath('data.0.email', $invite->email)
            ->assertJsonPath('data.0.role', User::ROLE_DENTIST)
            ->assertJsonPath('data.0.status', 'pending');
    }

    public function test_company_invites_list_only_shows_team_invites(): void
    {
        [$company, $admin] = $this->companyUser(User::ROLE_COMPANY_ADMIN);
        $dentistInvite = $this->invite($company, User::ROLE_DENTIST);
        $ownerInvite = $this->invite($company, User::ROLE_COMPANY_ADMIN);

        Sanctum::actingAs($admin);

        $this->getJson('/api/v1/company/invites')
            ->assertOk()
            ->assertJsonFragment(['id' => $dentistInvite->id, 'role' => User::ROLE_DENTIST])
            ->assertJsonMissing(['id' => $ownerInvite->id])
            ->assertJsonMissing(['role' => User::ROLE_COMPANY_ADMIN]);
    }

    public function test_company_admin_can_invite_dentist(): void
    {
        Mail::fake();
        [, $admin] = $this->companyUser(User::ROLE_COMPANY_ADMIN);

        Sanctum::actingAs($admin);

        Carbon::setTestNow(Carbon::parse('2026-05-12 10:00:00'));
        $sentAt = Carbon::now();

        $this->postJson('/api/v1/company/invites', [
            'email' => 'dentist@example.com',
            'role' => User::ROLE_DENTIST,
        ])
            ->assertCreated()
            ->assertJsonPath('data.email', 'dentist@example.com')
            ->assertJsonPath('data.role', User::ROLE_DENTIST)
            ->assertJsonPath('data.status', 'pending');

        $this->assertDatabaseHas('invites', [
            'company_id' => $admin->company_id,
            'email' => 'dentist@example.com',
            'role' => User::ROLE_DENTIST,
        ]);
        $invite = Invite::query()
            ->where('email', 'dentist@example.com')
            ->firstOrFail();
        $this->assertTrue($invite->expires_at->greaterThanOrEqualTo($sentAt->copy()->addMinutes(10)));
        Carbon::setTestNow();
        Mail::assertSent(InviteMail::class);
    }

    public function test_company_admin_can_invite_nurse(): void
    {
        Mail::fake();
        [, $admin] = $this->companyUser(User::ROLE_COMPANY_ADMIN);

        Sanctum::actingAs($admin);

        $this->postJson('/api/v1/company/invites', [
            'email' => 'nurse@example.com',
            'role' => User::ROLE_NURSE,
        ])
            ->assertCreated()
            ->assertJsonPath('data.email', 'nurse@example.com')
            ->assertJsonPath('data.role', User::ROLE_NURSE)
            ->assertJsonPath('data.status', 'pending');

        $this->assertDatabaseHas('invites', [
            'company_id' => $admin->company_id,
            'email' => 'nurse@example.com',
            'role' => User::ROLE_NURSE,
        ]);
        Mail::assertSent(InviteMail::class);
    }

    public function test_duplicate_pending_team_invite_is_blocked(): void
    {
        Mail::fake();
        [$company, $admin] = $this->companyUser(User::ROLE_COMPANY_ADMIN);
        $this->invite($company, User::ROLE_DENTIST)->forceFill([
            'email' => 'duplicate@example.com',
            'expires_at' => Carbon::now()->addMinutes(10),
        ])->save();

        Sanctum::actingAs($admin);

        $this->postJson('/api/v1/company/invites', [
            'email' => 'duplicate@example.com',
            'role' => User::ROLE_DENTIST,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);

        $this->assertSame(1, Invite::query()
            ->where('company_id', $company->id)
            ->where('email', 'duplicate@example.com')
            ->where('role', User::ROLE_DENTIST)
            ->count());
    }

    public function test_expired_or_revoked_team_invite_allows_new_invite(): void
    {
        Mail::fake();
        [$company, $admin] = $this->companyUser(User::ROLE_COMPANY_ADMIN);
        $expired = $this->invite($company, User::ROLE_DENTIST);
        $expired->forceFill([
            'email' => 'expired-team@example.com',
            'expires_at' => Carbon::now()->subMinute(),
        ])->save();
        $revoked = $this->invite($company, User::ROLE_NURSE);
        $revoked->forceFill([
            'email' => 'revoked-team@example.com',
            'revoked_at' => Carbon::now(),
        ])->save();

        Sanctum::actingAs($admin);

        $this->postJson('/api/v1/company/invites', [
            'email' => 'expired-team@example.com',
            'role' => User::ROLE_DENTIST,
        ])->assertCreated();

        $this->postJson('/api/v1/company/invites', [
            'email' => 'revoked-team@example.com',
            'role' => User::ROLE_NURSE,
        ])->assertCreated();

        $this->assertSame(2, Invite::query()->where('email', 'expired-team@example.com')->count());
        $this->assertSame(2, Invite::query()->where('email', 'revoked-team@example.com')->count());
    }

    public function test_company_admin_can_delete_pending_or_expired_invite_from_own_company(): void
    {
        [$company, $admin] = $this->companyUser(User::ROLE_COMPANY_ADMIN);
        $pendingInvite = $this->invite($company, User::ROLE_DENTIST);
        $expiredInvite = $this->invite($company, User::ROLE_NURSE);
        $expiredInvite->forceFill(['expires_at' => Carbon::now()->subDay()])->save();

        Sanctum::actingAs($admin);

        $this->deleteJson("/api/v1/company/invites/{$pendingInvite->id}")
            ->assertOk()
            ->assertJsonPath('data.deleted', true);

        $this->deleteJson("/api/v1/company/invites/{$expiredInvite->id}")
            ->assertOk()
            ->assertJsonPath('data.deleted', true);

        $this->assertNotNull($pendingInvite->fresh()->revoked_at);
        $this->assertNotNull($expiredInvite->fresh()->revoked_at);
    }

    public function test_company_admin_cannot_delete_accepted_invite_or_invite_from_other_company(): void
    {
        [$company, $admin] = $this->companyUser(User::ROLE_COMPANY_ADMIN);
        [$otherCompany] = $this->companyUser(User::ROLE_COMPANY_ADMIN, 'other-company');
        $acceptedInvite = $this->invite($company, User::ROLE_DENTIST);
        $acceptedInvite->forceFill([
            'accepted_at' => Carbon::now(),
            'accepted_by_user_id' => $admin->id,
        ])->save();
        $otherInvite = $this->invite($otherCompany, User::ROLE_NURSE);

        Sanctum::actingAs($admin);

        $this->deleteJson("/api/v1/company/invites/{$acceptedInvite->id}")
            ->assertForbidden()
            ->assertJsonPath('message', 'Prihvacene pozivnice ne mogu da se opozovu.');

        $this->deleteJson("/api/v1/company/invites/{$otherInvite->id}")
            ->assertNotFound();

        $this->assertDatabaseHas('invites', ['id' => $acceptedInvite->id]);
        $this->assertDatabaseHas('invites', ['id' => $otherInvite->id]);
    }

    public function test_dentist_and_nurse_cannot_delete_company_invite(): void
    {
        [$company, $dentist] = $this->companyUser(User::ROLE_DENTIST);
        $invite = $this->invite($company, User::ROLE_NURSE);

        Sanctum::actingAs($dentist);

        $this->deleteJson("/api/v1/company/invites/{$invite->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('invites', ['id' => $invite->id]);
    }

    public function test_dentist_and_nurse_cannot_invite_team_member(): void
    {
        [, $dentist] = $this->companyUser(User::ROLE_DENTIST);
        [, $nurse] = $this->companyUser(User::ROLE_NURSE, 'nurse-company');

        Sanctum::actingAs($dentist);
        $this->postJson('/api/v1/company/invites', [
            'email' => 'blocked-dentist@example.com',
            'role' => User::ROLE_DENTIST,
        ])->assertForbidden();

        Sanctum::actingAs($nurse);
        $this->postJson('/api/v1/company/invites', [
            'email' => 'blocked-nurse@example.com',
            'role' => User::ROLE_NURSE,
        ])->assertForbidden();

        $this->assertDatabaseMissing('invites', ['email' => 'blocked-dentist@example.com']);
        $this->assertDatabaseMissing('invites', ['email' => 'blocked-nurse@example.com']);
    }

    public function test_company_admin_can_delete_dentist_or_nurse_from_own_company(): void
    {
        [$company, $admin] = $this->companyUser(User::ROLE_COMPANY_ADMIN);
        $dentist = $this->user($company, User::ROLE_DENTIST, 'delete-dentist');

        Sanctum::actingAs($admin);

        $this->deleteJson("/api/v1/company/team/{$dentist->id}")
            ->assertOk()
            ->assertJsonPath('data.deleted', true)
            ->assertJsonPath('data.id', $dentist->id);

        $this->assertDatabaseMissing('users', [
            'id' => $dentist->id,
        ]);
    }

    public function test_deleting_team_member_keeps_accepted_invite_for_audit_history(): void
    {
        [$company, $admin] = $this->companyUser(User::ROLE_COMPANY_ADMIN);
        $dentist = $this->user($company, User::ROLE_DENTIST, 'accepted-dentist');
        $invite = $this->invite($company, User::ROLE_DENTIST);
        $invite->forceFill([
            'email' => $dentist->email,
            'accepted_at' => Carbon::now(),
            'accepted_by_user_id' => $dentist->id,
        ])->save();

        Sanctum::actingAs($admin);

        $this->deleteJson("/api/v1/company/team/{$dentist->id}")
            ->assertOk()
            ->assertJsonPath('data.deleted', true);

        $this->assertDatabaseMissing('users', ['id' => $dentist->id]);
        $this->assertDatabaseHas('invites', [
            'company_id' => $company->id,
            'email' => $dentist->email,
        ]);
    }

    public function test_company_admin_can_resend_pending_or_expired_invite(): void
    {
        Mail::fake();
        [$company, $admin] = $this->companyUser(User::ROLE_COMPANY_ADMIN);
        $pendingInvite = $this->invite($company, User::ROLE_DENTIST);
        $expiredInvite = $this->invite($company, User::ROLE_NURSE);
        $expiredInvite->forceFill(['expires_at' => Carbon::now()->subDay()])->save();
        $oldPendingToken = $pendingInvite->token;
        $oldExpiredToken = $expiredInvite->token;

        Sanctum::actingAs($admin);

        $this->postJson("/api/v1/company/invites/{$pendingInvite->id}/resend")
            ->assertOk()
            ->assertJsonPath('data.id', $pendingInvite->id);

        $this->postJson("/api/v1/company/invites/{$expiredInvite->id}/resend")
            ->assertOk()
            ->assertJsonPath('data.id', $expiredInvite->id);

        $this->assertNotSame($oldPendingToken, $pendingInvite->fresh()->token);
        $this->assertNotSame($oldExpiredToken, $expiredInvite->fresh()->token);
        $this->assertTrue($pendingInvite->fresh()->expires_at->greaterThan(Carbon::now()));
        $this->assertTrue($expiredInvite->fresh()->expires_at->greaterThan(Carbon::now()));
        Mail::assertSent(InviteMail::class, 2);
    }

    public function test_company_admin_cannot_resend_accepted_or_revoked_invite(): void
    {
        [$company, $admin] = $this->companyUser(User::ROLE_COMPANY_ADMIN);
        $acceptedInvite = $this->invite($company, User::ROLE_DENTIST);
        $acceptedInvite->forceFill([
            'accepted_at' => Carbon::now(),
            'accepted_by_user_id' => $admin->id,
        ])->save();
        $revokedInvite = $this->invite($company, User::ROLE_NURSE);
        $revokedInvite->forceFill(['revoked_at' => Carbon::now()])->save();

        Sanctum::actingAs($admin);

        $this->postJson("/api/v1/company/invites/{$acceptedInvite->id}/resend")
            ->assertUnprocessable();

        $this->postJson("/api/v1/company/invites/{$revokedInvite->id}/resend")
            ->assertUnprocessable();
    }

    public function test_company_admin_cannot_delete_company_admin_or_platform_admin(): void
    {
        [$company, $admin] = $this->companyUser(User::ROLE_COMPANY_ADMIN);
        $otherAdmin = $this->user($company, User::ROLE_COMPANY_ADMIN, 'other-admin');
        $platformAdmin = User::factory()->create([
            'company_id' => $company->id,
            'role' => User::ROLE_PLATFORM_ADMIN,
        ]);

        Sanctum::actingAs($admin);

        $this->deleteJson("/api/v1/company/team/{$otherAdmin->id}")
            ->assertForbidden()
            ->assertJsonPath('message', 'Nije dozvoljeno brisanje admin naloga.');

        $this->deleteJson("/api/v1/company/team/{$platformAdmin->id}")
            ->assertForbidden()
            ->assertJsonPath('message', 'Nije dozvoljeno brisanje admin naloga.');

        $this->assertDatabaseHas('users', ['id' => $otherAdmin->id]);
        $this->assertDatabaseHas('users', ['id' => $platformAdmin->id]);
    }

    public function test_company_admin_gets_404_when_deleting_user_from_other_company_or_missing_user(): void
    {
        [, $admin] = $this->companyUser(User::ROLE_COMPANY_ADMIN);
        [$otherCompany] = $this->companyUser(User::ROLE_COMPANY_ADMIN, 'other-company');
        $otherDentist = $this->user($otherCompany, User::ROLE_DENTIST, 'other-dentist');

        Sanctum::actingAs($admin);

        $this->deleteJson("/api/v1/company/team/{$otherDentist->id}")
            ->assertNotFound();

        $this->deleteJson('/api/v1/company/team/999999')
            ->assertNotFound();

        $this->assertDatabaseHas('users', ['id' => $otherDentist->id]);
    }

    public function test_dentist_and_nurse_cannot_delete_team_member(): void
    {
        [$company, $dentist] = $this->companyUser(User::ROLE_DENTIST);
        $nurse = $this->user($company, User::ROLE_NURSE, 'delete-target');

        Sanctum::actingAs($dentist);

        $this->deleteJson("/api/v1/company/team/{$nurse->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('users', ['id' => $nurse->id]);
    }

    public function test_platform_admin_cannot_use_company_team_api_without_company_context(): void
    {
        $platformAdmin = User::factory()->create([
            'company_id' => null,
            'role' => User::ROLE_PLATFORM_ADMIN,
        ]);

        Sanctum::actingAs($platformAdmin);

        $this->getJson('/api/v1/company/team')
            ->assertForbidden()
            ->assertHeader('content-type', 'application/json')
            ->assertJsonMissingPath('data');
    }

    public function test_unauthenticated_request_returns_401(): void
    {
        $this->getJson('/api/v1/company/team')
            ->assertUnauthorized()
            ->assertHeader('content-type', 'application/json')
            ->assertJsonMissingPath('data');
    }

    public function test_validation_error_returns_422(): void
    {
        [, $admin] = $this->companyUser(User::ROLE_COMPANY_ADMIN);

        Sanctum::actingAs($admin);

        $this->postJson('/api/v1/company/invites', [
            'email' => 'not-an-email',
            'role' => User::ROLE_COMPANY_ADMIN,
        ])
            ->assertUnprocessable()
            ->assertHeader('content-type', 'application/json')
            ->assertJsonValidationErrors(['email', 'role']);
    }

    /**
     * @return array{Company, User}
     */
    private function companyUser(string $role, string $suffix = 'main'): array
    {
        $company = Company::query()->create([
            'name' => "Company {$suffix}",
            'address' => "Address {$suffix}",
            'email' => "{$suffix}@example.com",
            'phone' => '060123456',
        ]);

        return [$company, $this->user($company, $role, $suffix)];
    }

    private function user(Company $company, string $role, string $suffix): User
    {
        return User::factory()->create([
            'company_id' => $company->id,
            'name' => "User {$suffix}",
            'first_name' => 'User',
            'last_name' => ucfirst($suffix),
            'phone' => '060123456',
            'role' => $role,
        ]);
    }

    private function invite(Company $company, string $role): Invite
    {
        return Invite::query()->create([
            'company_id' => $company->id,
            'email' => "{$role}@example.com",
            'role' => $role,
            'token' => 'token-'.str()->random(16),
            'expires_at' => Carbon::now()->addWeek(),
            'metadata' => [],
        ]);
    }
}
