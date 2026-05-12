<?php

namespace App\Services;

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
use RuntimeException;

class InviteService
{
    public function __construct(
        private readonly InviteRepositoryInterface $inviteRepository,
        private readonly CompanyRepositoryInterface $companyRepository,
        private readonly UserRepositoryInterface $userRepository,
    ) {}

    public function sendOwnerInvite(string $email, ?int $expiresInDays = null): Invite
    {
        $invite = $this->inviteRepository->create([
            'company_id' => null,
            'email' => mb_strtolower(trim($email)),
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
            throw new RuntimeException('Korisnik nema kompaniju.');
        }

        $invite = $this->inviteRepository->create([
            'company_id' => $inviter->company_id,
            'email' => mb_strtolower(trim($email)),
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
                    throw new RuntimeException('Pozivnica bez kompanije moze biti samo za company admin ulogu.');
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
