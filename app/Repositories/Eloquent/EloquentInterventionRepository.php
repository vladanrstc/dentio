<?php

namespace App\Repositories\Eloquent;

use App\Models\Intervention;
use App\Models\Patient;
use App\Repositories\Contracts\InterventionRepositoryInterface;
use Illuminate\Support\Collection;

class EloquentInterventionRepository implements InterventionRepositoryInterface
{
    public function create(array $data): Intervention
    {
        return Intervention::query()->create($data);
    }

    public function forPatient(Patient $patient): Collection
    {
        return Intervention::query()
            ->where('patient_id', $patient->id)
            ->with('performedBy')
            ->latest('intervention_date')
            ->get();
    }

    public function outstandingTotalForCompany(int $companyId): float
    {
        return (float) Intervention::query()
            ->where('company_id', $companyId)
            ->selectRaw('COALESCE(SUM(total_cost - paid_amount), 0) AS outstanding')
            ->value('outstanding');
    }
}
