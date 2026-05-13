<?php

namespace App\Services;

use App\Exceptions\InviteActionNotAllowedException;
use App\Exceptions\InviteResendNotAllowedException;
use App\Exceptions\MissingCompanyContextException;
use App\Exceptions\TenantResourceNotFoundException;
use App\Mail\InviteMail;
use App\Models\Company;
use App\Models\Invite;
use App\Models\User;
use App\Repositories\Contracts\CompanyRepositoryInterface;
use App\Repositories\Contracts\InviteRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class InviteService
{
    public function __construct(
        private readonly InviteRepositoryInterface $inviteRepository,
        private readonly CompanyRepositoryInterface $companyRepository,
        private readonly UserRepositoryInterface $userRepository,
    ) {}

    public function sendOwnerInvite(string $email, ?int $expiresInDays = null): Invite
    {
        $email = mb_strtolower(trim($email));
        $this->ensureNoActiveDuplicateInvite(null, $email, User::ROLE_COMPANY_ADMIN);

        $invite = $this->inviteRepository->create([
            'company_id' => null,
            'email' => $email,
            'role' => User::ROLE_COMPANY_ADMIN,
            'token' => Str::random(64),
            'invited_by_user_id' => null,
            'expires_at' => $this->expiresAt($expiresInDays),
            'metadata' => [],
        ]);

        Mail::to($invite->email)->send(new InviteMail($invite));

        return $invite;
    }

    public function sendStaffInvite(User $inviter, string $email, string $role, ?int $expiresInDays = null): Invite
    {
        if (! $inviter->company_id) {
            throw new MissingCompanyContextException(__('errors.missing_company'));
        }

        $email = mb_strtolower(trim($email));
        $this->ensureNoActiveDuplicateInvite((int) $inviter->company_id, $email, $role);

        $invite = $this->inviteRepository->create([
            'company_id' => $inviter->company_id,
            'email' => $email,
            'role' => $role,
            'token' => Str::random(64),
            'invited_by_user_id' => $inviter->id,
            'expires_at' => $this->expiresAt($expiresInDays),
            'metadata' => [],
        ]);

        Mail::to($invite->email)->send(new InviteMail($invite));

        return $invite;
    }

    public function findValidInviteByToken(string $token): ?Invite
    {
        return $this->inviteRepository->findValidByToken($token);
    }

    public function findInviteByToken(string $token): ?Invite
    {
        return $this->inviteRepository->findByToken($token);
    }

    public function acceptInvite(Invite $invite, array $data): User
    {
        return DB::transaction(function () use ($invite, $data): User {
            $company = null;
            $companyId = $invite->company_id;

            if ($companyId === null) {
                if ($invite->role !== User::ROLE_COMPANY_ADMIN) {
                    throw new InviteActionNotAllowedException(__('errors.unauthorized'));
                }

                $company = $this->companyRepository->create([
                    'name' => $data['company_name'],
                    'address' => $data['company_address'],
                    'email' => $invite->email,
                    'phone' => $data['company_phone'] ?? null,
                ]);
                $companyId = $company->id;
            }

            $firstName = trim((string) $data['first_name']);
            $lastName = trim((string) $data['last_name']);
            $name = trim($firstName.' '.$lastName);

            $user = $this->userRepository->create([
                'company_id' => $companyId,
                'name' => $name !== '' ? $name : $invite->email,
                'first_name' => $firstName !== '' ? $firstName : null,
                'last_name' => $lastName !== '' ? $lastName : null,
                'phone' => $data['phone'] ?? null,
                'role' => $invite->role,
                'email' => $invite->email,
                'password' => $data['password'],
            ]);

            $this->inviteRepository->markAccepted($invite, $user->id);

            if ($company instanceof Company) {
                $this->companyRepository->updateCreatedBy($company, $user->id);
            }

            return $user;
        });
    }

    public function revokeInvite(Invite $invite): Invite
    {
        if ($invite->accepted_at !== null) {
            throw new InviteActionNotAllowedException(__('errors.invite_accepted_cannot_revoke'));
        }

        if ($invite->revoked_at === null) {
            $invite->revoked_at = Carbon::now();
            $invite->save();
        }

        return $invite;
    }

    public function revokeTeamInviteForCompany(int $companyId, int $inviteId): Invite
    {
        $invite = $this->inviteRepository->findTeamInviteForCompany($companyId, $inviteId);

        if ($invite === null) {
            throw new TenantResourceNotFoundException(__('errors.invite_not_found'));
        }

        return $this->revokeInvite($invite);
    }

    public function resendInvite(Invite $invite): Invite
    {
        if ($invite->accepted_at !== null || $invite->revoked_at !== null) {
            throw new InviteResendNotAllowedException(__('errors.invite_resend_not_allowed'));
        }

        $invite->token = Str::random(64);
        $invite->expires_at = Carbon::now()->addMinutes(10);
        $invite->save();

        Mail::to($invite->email)->send(new InviteMail($invite));

        return $invite;
    }

    public function resendTeamInviteForCompany(int $companyId, int $inviteId): Invite
    {
        $invite = $this->inviteRepository->findTeamInviteForCompany($companyId, $inviteId);

        if ($invite === null) {
            throw new TenantResourceNotFoundException(__('errors.invite_not_found'));
        }

        return $this->resendInvite($invite);
    }

    private function ensureNoActiveDuplicateInvite(?int $companyId, string $email, string $role): void
    {
        if ($this->inviteRepository->hasActiveDuplicate($companyId, $email, $role)) {
            throw ValidationException::withMessages([
                'email' => [__('errors.invite_duplicate_active')],
            ]);
        }
    }

    private function expiresAt(?int $expiresInDays = null): Carbon
    {
        $minimum = Carbon::now()->addMinutes(10);

        if ($expiresInDays === null) {
            return $minimum;
        }

        $requested = Carbon::now()->addDays($expiresInDays);

        return $requested->greaterThan($minimum) ? $requested : $minimum;
    }
}
