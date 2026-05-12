<?php

namespace Tests\Feature\Api;

use App\Models\Company;
use App\Models\Invite;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class InviteAcceptanceApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_view_valid_company_owner_invite_through_api(): void
    {
        $invite = $this->invite(null, User::ROLE_COMPANY_ADMIN);

        $this->getJson("/api/v1/invites/accept/{$invite->token}")
            ->assertOk()
            ->assertJsonPath('data.email', $invite->email)
            ->assertJsonPath('data.role', User::ROLE_COMPANY_ADMIN)
            ->assertJsonPath('data.valid', true)
            ->assertJsonPath('data.accepted', false)
            ->assertJsonPath('data.requires_company', true);
    }

    public function test_can_accept_company_owner_invite_through_api_and_create_company(): void
    {
        $invite = $this->invite(null, User::ROLE_COMPANY_ADMIN, 'owner@example.com');

        $this->postJson("/api/v1/invites/accept/{$invite->token}", [
            'first_name' => 'Owner',
            'last_name' => 'Example',
            'phone' => '060123456',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'company_name' => 'Nova Ordinacija',
            'company_address' => 'Nova adresa 1',
            'company_phone' => '011123456',
        ])
            ->assertCreated()
            ->assertJsonPath('data.user.email', 'owner@example.com')
            ->assertJsonPath('data.user.role', User::ROLE_COMPANY_ADMIN)
            ->assertJsonPath('data.company.name', 'Nova Ordinacija')
            ->assertJsonPath('data.requires_company', true);

        $this->assertDatabaseHas('companies', [
            'name' => 'Nova Ordinacija',
            'address' => 'Nova adresa 1',
            'email' => 'owner@example.com',
        ]);
        $this->assertDatabaseHas('users', [
            'email' => 'owner@example.com',
            'role' => User::ROLE_COMPANY_ADMIN,
        ]);
        $this->assertNotNull($invite->fresh()->accepted_at);
    }

    public function test_can_view_valid_staff_invite_through_api(): void
    {
        $company = $this->company();
        $invite = $this->invite($company, User::ROLE_DENTIST);

        $this->getJson("/api/v1/invites/accept/{$invite->token}")
            ->assertOk()
            ->assertJsonPath('data.email', $invite->email)
            ->assertJsonPath('data.role', User::ROLE_DENTIST)
            ->assertJsonPath('data.valid', true)
            ->assertJsonPath('data.requires_company', false);
    }

    public function test_can_accept_staff_invite_through_api(): void
    {
        $company = $this->company();
        $invite = $this->invite($company, User::ROLE_NURSE, 'nurse@example.com');

        $this->postJson("/api/v1/invites/accept/{$invite->token}", [
            'first_name' => 'Nurse',
            'last_name' => 'Example',
            'phone' => '060123456',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ])
            ->assertCreated()
            ->assertJsonPath('data.user.email', 'nurse@example.com')
            ->assertJsonPath('data.user.company_id', $company->id)
            ->assertJsonPath('data.user.role', User::ROLE_NURSE)
            ->assertJsonPath('data.company.id', $company->id)
            ->assertJsonPath('data.requires_company', false);

        $this->assertDatabaseHas('users', [
            'company_id' => $company->id,
            'email' => 'nurse@example.com',
            'role' => User::ROLE_NURSE,
        ]);
    }

    public function test_expired_or_invalid_token_returns_error_json(): void
    {
        $expired = $this->invite(null, User::ROLE_COMPANY_ADMIN);
        $expired->forceFill(['expires_at' => Carbon::now()->subDay()])->save();

        $this->getJson('/api/v1/invites/accept/missing-token')
            ->assertNotFound()
            ->assertHeader('content-type', 'application/json')
            ->assertJsonMissingPath('data');

        $this->getJson("/api/v1/invites/accept/{$expired->token}")
            ->assertOk()
            ->assertJsonPath('data.valid', false)
            ->assertJsonPath('data.expired', true);

        $this->postJson("/api/v1/invites/accept/{$expired->token}", $this->ownerPayload())
            ->assertUnprocessable()
            ->assertHeader('content-type', 'application/json')
            ->assertJsonPath('message', 'Pozivnica je istekla.')
            ->assertJsonMissingPath('data');
    }

    public function test_reused_invite_cannot_be_accepted_again(): void
    {
        $invite = $this->invite(null, User::ROLE_COMPANY_ADMIN);
        $acceptedBy = User::factory()->create([
            'company_id' => null,
            'role' => User::ROLE_PLATFORM_ADMIN,
            'password' => Hash::make('password'),
        ]);
        $invite->forceFill([
            'accepted_at' => Carbon::now(),
            'accepted_by_user_id' => $acceptedBy->id,
        ])->save();

        $this->getJson("/api/v1/invites/accept/{$invite->token}")
            ->assertOk()
            ->assertJsonPath('data.valid', false)
            ->assertJsonPath('data.accepted', true);

        $this->postJson("/api/v1/invites/accept/{$invite->token}", $this->ownerPayload())
            ->assertGone()
            ->assertHeader('content-type', 'application/json');
    }

    public function test_validation_errors_return_422(): void
    {
        $invite = $this->invite(null, User::ROLE_COMPANY_ADMIN);

        $this->postJson("/api/v1/invites/accept/{$invite->token}", [
            'first_name' => '',
            'last_name' => '',
            'password' => 'short',
            'password_confirmation' => 'different',
        ])
            ->assertUnprocessable()
            ->assertHeader('content-type', 'application/json')
            ->assertJsonValidationErrors(['first_name', 'last_name', 'password']);
    }

    public function test_existing_user_email_returns_422_without_sql_exception(): void
    {
        $invite = $this->invite(null, User::ROLE_COMPANY_ADMIN, 'existing@example.com');
        User::factory()->create([
            'company_id' => null,
            'email' => 'existing@example.com',
            'role' => User::ROLE_PLATFORM_ADMIN,
        ]);

        $this->postJson("/api/v1/invites/accept/{$invite->token}", $this->ownerPayload())
            ->assertUnprocessable()
            ->assertHeader('content-type', 'application/json')
            ->assertJsonPath('message', 'Korisnik sa ovom email adresom već postoji.');

        $this->assertNull($invite->fresh()->accepted_at);
    }

    private function company(): Company
    {
        return Company::query()->create([
            'name' => 'Test Ordinacija',
            'address' => 'Test adresa',
            'email' => 'office@example.com',
            'phone' => '060123456',
        ]);
    }

    private function invite(?Company $company, string $role, string $email = 'invite@example.com'): Invite
    {
        return Invite::query()->create([
            'company_id' => $company?->id,
            'email' => $email,
            'role' => $role,
            'token' => 'token-'.str()->random(24),
            'expires_at' => Carbon::now()->addWeek(),
            'metadata' => [],
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function ownerPayload(): array
    {
        return [
            'first_name' => 'Owner',
            'last_name' => 'Example',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'company_name' => 'Nova Ordinacija',
            'company_address' => 'Nova adresa 1',
        ];
    }
}
