<?php

namespace App\Repositories\Eloquent;

use App\Models\Patient;
use App\Models\PatientTask;
use App\Repositories\Contracts\PatientTaskRepositoryInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class EloquentPatientTaskRepository implements PatientTaskRepositoryInterface
{
    public function create(array $data): PatientTask
    {
        return PatientTask::query()->create($data);
    }

    public function findForCompanyPatient(int $companyId, int $patientId, int $taskId): ?PatientTask
    {
        return PatientTask::query()
            ->where('company_id', $companyId)
            ->where('patient_id', $patientId)
            ->whereKey($taskId)
            ->first();
    }

    public function markDone(PatientTask $task, int $userId): PatientTask
    {
        $task->status = PatientTask::STATUS_DONE;
        $task->closed_by_user_id = $userId;
        $task->closed_at = Carbon::now();
        $task->save();

        return $task;
    }

    public function openForPatient(Patient $patient): Collection
    {
        return PatientTask::query()
            ->where('patient_id', $patient->id)
            ->where('status', PatientTask::STATUS_OPEN)
            ->with('assignedTo')
            ->orderBy('due_date')
            ->latest('id')
            ->get();
    }
}

