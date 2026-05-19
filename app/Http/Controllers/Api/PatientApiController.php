<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PatientCollection;
use App\Http\Resources\PatientResource;
use App\Models\Patient;
use App\Models\PatientTask;
use App\Services\PatientService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class PatientApiController extends Controller
{
    public function __construct(
        private readonly PatientService $patientService,
    ) {
    }

    public function index(Request $request): PatientCollection
    {
        $search = $request->string('search')->toString();
        $patients = $this->patientService->paginateForUser($request->user(), $search !== '' ? $search : null);

        return new PatientCollection($patients);
    }

    public function show(Request $request, Patient $patient): PatientResource
    {
        Gate::authorize('view', $patient);
        $patient->loadMissing([
            'primaryDentist',
            'appointments.scheduledBy',
            'appointments.assignedTo',
            'interventions.performedBy',
            'tasks.assignedTo',
            'statusLogs.changedBy',
        ])->loadCount([
            'tasks as open_tasks_count' => fn ($query) => $query->where('status', PatientTask::STATUS_OPEN),
        ]);

        return new PatientResource($patient);
    }
}

