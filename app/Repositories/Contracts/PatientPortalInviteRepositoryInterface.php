<?php

namespace App\Repositories\Contracts;

use App\Models\PatientPortalInvite;

interface PatientPortalInviteRepositoryInterface
{
    public function create(array $data): PatientPortalInvite;

    public function findByTokenHash(string $tokenHash): ?PatientPortalInvite;

    public function findReusableForPatient(int $companyId, int $patientId): ?PatientPortalInvite;

    public function revokeOtherOpenForPatient(int $patientId, int $exceptInviteId): void;
}
