<?php

namespace App\Services;

use App\Exceptions\MissingCompanyContextException;
use App\Mail\PatientPortalInviteMail;
use App\Models\Patient;
use App\Models\PatientPortalInvite;
use App\Models\User;
use App\Repositories\Contracts\PatientPortalInviteRepositoryInterface;
use App\Repositories\Contracts\PatientRepositoryInterface;
use App\Services\Contracts\PatientPortalInviteServiceInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class PatientPortalInviteService implements PatientPortalInviteServiceInterface
{
    public function __construct(
        private readonly PatientRepositoryInterface $patients,
        private readonly PatientPortalInviteRepositoryInterface $invites,
    ) {}

    public function send(User $inviter, string $email): PatientPortalInvite
    {
        if (! $inviter->company_id) {
            throw new MissingCompanyContextException(__('errors.missing_company'));
        }

        $email = mb_strtolower(trim($email));
        $patient = $this->patients->findForCompanyByEmail((int) $inviter->company_id, $email);

        if (! $patient) {
            throw ValidationException::withMessages([
                'email' => [__('errors.patient_portal_patient_not_found')],
            ]);
        }

        $token = Str::random(64);
        $invite = $this->invites->findReusableForPatient((int) $inviter->company_id, $patient->id);

        if ($invite) {
            $invite->fill([
                'email' => $email,
                'token_hash' => $this->hashToken($token),
                'invited_by_user_id' => $inviter->id,
                'expires_at' => Carbon::now()->addMinutes(10),
            ])->save();
        } else {
            $invite = $this->invites->create([
                'company_id' => $inviter->company_id,
                'patient_id' => $patient->id,
                'invited_by_user_id' => $inviter->id,
                'email' => $email,
                'token_hash' => $this->hashToken($token),
                'expires_at' => Carbon::now()->addMinutes(10),
            ]);
        }

        Mail::to($invite->email)->send(new PatientPortalInviteMail($invite, $token));

        return $invite;
    }

    public function findByToken(string $token): ?PatientPortalInvite
    {
        return $this->invites->findByTokenHash($this->hashToken($token));
    }

    public function accept(string $token, string $password): Patient
    {
        $invite = $this->findByToken($token);

        if (! $invite) {
            throw new NotFoundHttpException(__('errors.invite_not_found'));
        }

        if ($invite->accepted_at !== null) {
            throw ValidationException::withMessages([
                'token' => [__('errors.invite_already_accepted')],
            ]);
        }

        if ($invite->revoked_at !== null) {
            throw ValidationException::withMessages([
                'token' => [__('errors.invite_revoked')],
            ]);
        }

        if ($invite->expires_at->isPast()) {
            throw ValidationException::withMessages([
                'token' => [__('errors.invite_expired')],
            ]);
        }

        return DB::transaction(function () use ($invite, $password): Patient {
            $patient = $invite->patient;
            $patient->password = $password;
            $patient->save();

            $invite->accepted_at = Carbon::now();
            $invite->save();

            $this->invites->revokeOtherOpenForPatient($patient->id, $invite->id);

            return $patient;
        });
    }

    private function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }
}
