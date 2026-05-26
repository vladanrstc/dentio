<?php

namespace App\Services;

use App\Models\Patient;
use App\Models\PatientTask;
use App\Repositories\Contracts\PatientRepositoryInterface;
use App\Services\Contracts\ClientPortalServiceInterface;
use Illuminate\Support\Collection;

class ClientPortalService implements ClientPortalServiceInterface
{
    public function __construct(
        private readonly PatientRepositoryInterface $patients,
    ) {}

    public function profile(Patient $patient): Patient
    {
        return $this->patients->findClientProfile($patient->id) ?? $patient;
    }

    public function appointments(Patient $patient): Collection
    {
        return $patient->appointments()
            ->with(['patient', 'assignedTo', 'scheduledBy'])
            ->latest('starts_at')
            ->get();
    }

    public function interventions(Patient $patient): Collection
    {
        return $patient->interventions()
            ->with(['performedBy'])
            ->latest('intervention_date')
            ->latest('id')
            ->get();
    }

    public function tasks(Patient $patient): Collection
    {
        return $patient->tasks()
            ->with(['assignedTo', 'createdBy', 'closedBy'])
            ->where('status', PatientTask::STATUS_OPEN)
            ->orderBy('due_date')
            ->latest('id')
            ->get();
    }
}
