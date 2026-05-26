<?php

namespace App\Repositories\Eloquent;

use App\Models\PatientPortalInvite;
use App\Repositories\Contracts\PatientPortalInviteRepositoryInterface;
use Illuminate\Support\Carbon;

class EloquentPatientPortalInviteRepository implements PatientPortalInviteRepositoryInterface
{
    public function create(array $data): PatientPortalInvite
    {
        return PatientPortalInvite::query()->create($data);
    }

    public function findByTokenHash(string $tokenHash): ?PatientPortalInvite
    {
        return PatientPortalInvite::query()
            ->where('token_hash', $tokenHash)
            ->with(['patient', 'company'])
            ->first();
    }

    public function findReusableForPatient(int $companyId, int $patientId): ?PatientPortalInvite
    {
        return PatientPortalInvite::query()
            ->where('company_id', $companyId)
            ->where('patient_id', $patientId)
            ->whereNull('accepted_at')
            ->whereNull('revoked_at')
            ->latest()
            ->first();
    }

    public function revokeOtherOpenForPatient(int $patientId, int $exceptInviteId): void
    {
        PatientPortalInvite::query()
            ->where('patient_id', $patientId)
            ->whereKeyNot($exceptInviteId)
            ->whereNull('accepted_at')
            ->whereNull('revoked_at')
            ->update(['revoked_at' => Carbon::now()]);
    }
}
