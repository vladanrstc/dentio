<?php

namespace App\Repositories\Eloquent;

use App\Models\Patient;
use App\Repositories\Contracts\PatientRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class EloquentPatientRepository implements PatientRepositoryInterface
{
    public function paginateForCompany(int $companyId, ?string $search, int $perPage = 15): LengthAwarePaginator
    {
        return Patient::query()
            ->where('company_id', $companyId)
            ->when($search, function (Builder $query, string $searchTerm): void {
                $query->where(function (Builder $inner) use ($searchTerm): void {
                    $inner->where('first_name', 'like', '%'.$searchTerm.'%')
                        ->orWhere('last_name', 'like', '%'.$searchTerm.'%')
                        ->orWhere('phone', 'like', '%'.$searchTerm.'%');
                });
            })
            ->with(['primaryDentist'])
            ->withCount([
                'tasks as open_tasks_count' => fn (Builder $query) => $query->where('status', 'open'),
            ])
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->paginate($perPage);
    }

    public function createForCompany(int $companyId, array $data): Patient
    {
        $data['company_id'] = $companyId;

        return Patient::query()->create($data);
    }

    public function findForCompany(int $companyId, int $patientId): ?Patient
    {
        return Patient::query()
            ->where('company_id', $companyId)
            ->whereKey($patientId)
            ->first();
    }

    public function findWithRelationsForCompany(int $companyId, int $patientId): ?Patient
    {
        return Patient::query()
            ->where('company_id', $companyId)
            ->whereKey($patientId)
            ->with([
                'primaryDentist',
                'appointments.scheduledBy',
                'appointments.assignedTo',
                'interventions.performedBy',
                'tasks.assignedTo',
                'statusLogs.changedBy',
            ])
            ->withCount([
                'tasks as open_tasks_count' => fn (Builder $query) => $query->where('status', 'open'),
            ])
            ->first();
    }

    public function update(Patient $patient, array $data): Patient
    {
        $patient->fill($data);
        $patient->save();

        return $patient;
    }

    public function countForCompany(int $companyId): int
    {
        return Patient::query()
            ->where('company_id', $companyId)
            ->count();
    }

    public function countWithOpenTasksForCompany(int $companyId): int
    {
        return Patient::query()
            ->where('company_id', $companyId)
            ->whereHas('tasks', fn (Builder $query) => $query->where('status', 'open'))
            ->count();
    }
}

