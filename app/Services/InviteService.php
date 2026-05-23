<?php

namespace App\Services;

use App\Mail\InviteMail;
use App\Models\Company;
use App\Models\Invite;
use App\Models\Patient;
use App\Models\User;
use App\Repositories\Contracts\CompanyRepositoryInterface;
use App\Repositories\Contracts\InviteRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class InviteService
{
    public function __construct(
        private readonly InviteRepositoryInterface $inviteRepository,
        private readonly CompanyRepositoryInterface $companyRepository,
        private readonly UserRepositoryInterface $userRepository,
    ) {
    }

    public function sendOwnerInvite(string $email, ?int $expiresInDays = null): Invite
    {
        $invite = $this->inviteRepository->create([
            'company_id' => null,
            'email' => mb_strtolower(trim($email)),
            'role' => User::ROLE_COMPANY_ADMIN,
            'token' => Str::random(32),
            'invited_by_user_id' => null,
            'expires_at' => Carbon::now()->addDays($expiresInDays ?? 14),
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
            'token' => Str::random(32),
            'invited_by_user_id' => $inviter->id,
            'expires_at' => Carbon::now()->addDays($expiresInDays ?? 7),
            'metadata' => [],
        ]);

        Mail::to($invite->email)->send(new InviteMail($invite));

        return $invite;
    }

    public function sendPatientInvite(User $inviter, Patient $patient, ?int $expiresInDays = null): Invite
    {
        if (! $inviter->company_id || $inviter->company_id !== $patient->company_id) {
            throw new RuntimeException('Pacijent ne pripada kompaniji korisnika.');
        }

        if ($patient->user_id !== null) {
            throw new RuntimeException('Pacijent vec ima povezan portal nalog.');
        }

        $email = mb_strtolower(trim((string) $patient->email));
        if ($email === '') {
            throw new RuntimeException('Pacijent nema email adresu za slanje pozivnice.');
        }

        $existingInvite = Invite::query()
            ->where('company_id', $patient->company_id)
            ->where('email', $email)
            ->where('role', User::ROLE_PATIENT)
            ->whereNull('accepted_at')
            ->where('expires_at', '>', Carbon::now())
            ->where('metadata->patient_id', $patient->id)
            ->first();

        if ($existingInvite) {
            Mail::to($existingInvite->email)->send(new InviteMail($existingInvite));

            return $existingInvite;
        }

        $invite = $this->inviteRepository->create([
            'company_id' => $patient->company_id,
            'email' => $email,
            'role' => User::ROLE_PATIENT,
            'token' => Str::random(32),
            'invited_by_user_id' => $inviter->id,
            'expires_at' => Carbon::now()->addDays($expiresInDays ?? 7),
            'metadata' => [
                'patient_id' => $patient->id,
            ],
        ]);

        Mail::to($invite->email)->send(new InviteMail($invite));

        return $invite;
    }

    public function findValidInviteByToken(string $token): ?Invite
    {
        return $this->inviteRepository->findValidByToken($token);
    }

    public function acceptInvite(Invite $invite, array $data): User
    {
        return DB::transaction(function () use ($invite, $data): User {
            $company = null;
            $companyId = $invite->company_id;
            $patient = null;

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

            if ($invite->role === User::ROLE_PATIENT) {
                $patient = $this->patientForInvite($invite, (int) $companyId);
            }

            $firstName = $patient ? $patient->first_name : trim((string) $data['first_name']);
            $lastName = $patient ? $patient->last_name : trim((string) $data['last_name']);
            $name = trim($firstName.' '.$lastName);

            $user = $this->userRepository->create([
                'company_id' => $companyId,
                'name' => $name !== '' ? $name : $invite->email,
                'first_name' => $firstName !== '' ? $firstName : null,
                'last_name' => $lastName !== '' ? $lastName : null,
                'phone' => $patient ? $patient->phone : ($data['phone'] ?? null),
                'role' => $invite->role,
                'email' => $invite->email,
                'password' => $data['password'],
            ]);

            if ($patient) {
                $patient->update([
                    'user_id' => $user->id,
                    'email' => $invite->email,
                ]);
            }

            $this->inviteRepository->markAccepted($invite, $user->id);

            if ($company instanceof Company) {
                $this->companyRepository->updateCreatedBy($company, $user->id);
            }

            return $user;
        });
    }

    private function patientForInvite(Invite $invite, int $companyId): Patient
    {
        $patientId = $invite->metadata['patient_id'] ?? null;

        if (! $patientId) {
            throw ValidationException::withMessages([
                'invite' => ['Pozivnica nije povezana sa pacijentom.'],
            ]);
        }

        $patient = Patient::query()
            ->whereKey($patientId)
            ->where('company_id', $companyId)
            ->first();

        if (! $patient) {
            throw ValidationException::withMessages([
                'invite' => ['Pacijent za ovu pozivnicu nije pronadjen.'],
            ]);
        }

        if ($patient->user_id !== null) {
            throw ValidationException::withMessages([
                'invite' => ['Pacijent vec ima povezan portal nalog.'],
            ]);
        }

        return $patient;
    }
}

