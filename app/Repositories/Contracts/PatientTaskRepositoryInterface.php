<?php

namespace App\Repositories\Contracts;

use App\Models\Patient;
use App\Models\PatientTask;
use Illuminate\Support\Collection;

interface PatientTaskRepositoryInterface
{
    public function create(array $data): PatientTask;

    public function findForCompanyPatient(int $companyId, int $patientId, int $taskId): ?PatientTask;

    public function markDone(PatientTask $task, int $userId): PatientTask;

    public function openForPatient(Patient $patient): Collection;
}

