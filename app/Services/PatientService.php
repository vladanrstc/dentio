<?php

namespace App\Services;

use App\Models\Patient;
use App\Models\PatientStatusLog;
use App\Models\PatientTask;
use App\Models\User;
use App\Repositories\Contracts\PatientRepositoryInterface;
use App\Repositories\Contracts\PatientTaskRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use RuntimeException;

class PatientService
{
    public function __construct(
        private readonly PatientRepositoryInterface $patientRepository,
        private readonly PatientTaskRepositoryInterface $patientTaskRepository,
    ) {
    }

    public function paginateForUser(User $user, ?string $search, int $perPage = 15): LengthAwarePaginator
    {
        $companyId = $this->companyIdOrFail($user);

        return $this->patientRepository->paginateForCompany($companyId, $search, $perPage);
    }

    public function create(User $user, array $data): Patient
    {
        $companyId = $this->companyIdOrFail($user);

        return $this->patientRepository->createForCompany($companyId, $data);
    }

    public function findForUser(User $user, int $patientId, bool $withRelations = false): ?Patient
    {
        $companyId = $this->companyIdOrFail($user);

        return $withRelations
            ? $this->patientRepository->findWithRelationsForCompany($companyId, $patientId)
            : $this->patientRepository->findForCompany($companyId, $patientId);
    }

    public function update(User $user, Patient $patient, array $data): Patient
    {
        if ($patient->company_id !== $this->companyIdOrFail($user)) {
            throw new RuntimeException('Pacijent ne pripada kompaniji korisnika.');
        }

        return $this->patientRepository->update($patient, $data);
    }

    public function changeManualStatus(User $user, Patient $patient, string $newStatus, ?string $reason): Patient
    {
        if ($patient->company_id !== $this->companyIdOrFail($user)) {
            throw new RuntimeException('Pacijent ne pripada kompaniji korisnika.');
        }

        $previousStatus = $patient->manual_status;

        $patient = $this->patientRepository->update($patient, [
            'manual_status' => $newStatus,
            'manual_status_reason' => $reason,
            'manual_status_changed_at' => Carbon::now(),
            'manual_status_changed_by_user_id' => $user->id,
        ]);

        PatientStatusLog::query()->create([
            'company_id' => $patient->company_id,
            'patient_id' => $patient->id,
            'changed_by_user_id' => $user->id,
            'previous_status' => $previousStatus,
            'new_status' => $newStatus,
            'reason' => $reason,
        ]);

        return $patient;
    }

    public function addTask(User $user, Patient $patient, array $data): PatientTask
    {
        if ($patient->company_id !== $this->companyIdOrFail($user)) {
            throw new RuntimeException('Pacijent ne pripada kompaniji korisnika.');
        }

        return $this->patientTaskRepository->create([
            'company_id' => $patient->company_id,
            'patient_id' => $patient->id,
            'created_by_user_id' => $user->id,
            'assigned_to_user_id' => $data['assigned_to_user_id'] ?? null,
            'description' => $data['description'],
            'due_date' => $data['due_date'] ?? null,
            'status' => PatientTask::STATUS_OPEN,
        ]);
    }

    public function completeTask(User $user, PatientTask $task): PatientTask
    {
        if ($task->company_id !== $this->companyIdOrFail($user)) {
            throw new RuntimeException('Task ne pripada kompaniji korisnika.');
        }

        if ($task->status === PatientTask::STATUS_DONE) {
            return $task;
        }

        return $this->patientTaskRepository->markDone($task, $user->id);
    }

    private function companyIdOrFail(User $user): int
    {
        if (! $user->company_id) {
            throw new RuntimeException('Korisnik nema dodeljenu kompaniju.');
        }

        return (int) $user->company_id;
    }
}

