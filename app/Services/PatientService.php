<?php

namespace App\Services;

use App\Exceptions\MissingCompanyContextException;
use App\Exceptions\TenantResourceNotFoundException;
use App\Models\Appointment;
use App\Models\Intervention;
use App\Models\Patient;
use App\Models\PatientStatusLog;
use App\Models\PatientTask;
use App\Models\Reminder;
use App\Models\User;
use App\Repositories\Contracts\PatientRepositoryInterface;
use App\Repositories\Contracts\PatientTaskRepositoryInterface;
use App\Services\Contracts\PatientServiceInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class PatientService implements PatientServiceInterface
{
    public function __construct(
        private readonly PatientRepositoryInterface $patientRepository,
        private readonly PatientTaskRepositoryInterface $patientTaskRepository,
    ) {}

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
        $this->assertAccessible($user, $patient);

        return $this->patientRepository->update($patient, $data);
    }

    public function assertAccessible(User $user, Patient $patient): Patient
    {
        if ($patient->company_id !== $this->companyIdOrFail($user)) {
            throw new TenantResourceNotFoundException(__('errors.patient_not_found'));
        }

        return $patient;
    }

    public function delete(User $user, Patient $patient): void
    {
        $this->assertAccessible($user, $patient);

        DB::transaction(function () use ($patient): void {
            $appointmentIds = Appointment::query()
                ->where('patient_id', $patient->id)
                ->pluck('id');
            $interventionIds = Intervention::query()
                ->where('patient_id', $patient->id)
                ->pluck('id');

            Reminder::query()
                ->where(function (Builder $query) use ($patient, $appointmentIds, $interventionIds): void {
                    $query->where('patient_id', $patient->id)
                        ->when($appointmentIds->isNotEmpty(), fn (Builder $inner) => $inner->orWhereIn('appointment_id', $appointmentIds))
                        ->when($interventionIds->isNotEmpty(), fn (Builder $inner) => $inner->orWhereIn('intervention_id', $interventionIds));
                })
                ->delete();

            PatientTask::query()
                ->where('patient_id', $patient->id)
                ->delete();
            Intervention::query()
                ->where('patient_id', $patient->id)
                ->delete();
            Appointment::query()
                ->where('patient_id', $patient->id)
                ->delete();

            $patient->delete();
        });
    }

    public function changeManualStatus(User $user, Patient $patient, string $newStatus, ?string $reason): Patient
    {
        $this->assertAccessible($user, $patient);

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
        $this->assertAccessible($user, $patient);

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
            throw new TenantResourceNotFoundException(__('errors.task_not_found'));
        }

        if ($task->status === PatientTask::STATUS_DONE) {
            return $task;
        }

        return $this->patientTaskRepository->markDone($task, $user->id);
    }

    private function companyIdOrFail(User $user): int
    {
        if (! $user->company_id) {
            throw new MissingCompanyContextException(__('errors.missing_company'));
        }

        return (int) $user->company_id;
    }
}
