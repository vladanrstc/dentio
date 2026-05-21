<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\Patient\IndexPatientRequest;
use App\Http\Requests\Appointment\StoreAppointmentRequest;
use App\Http\Requests\Intervention\StoreInterventionRequest;
use App\Http\Requests\Patient\StorePatientRequest;
use App\Http\Requests\Patient\StorePatientTaskRequest;
use App\Http\Requests\Patient\UpdatePatientRequest;
use App\Http\Requests\Patient\UpdatePatientStatusRequest;
use App\Http\Resources\Api\V1\AppointmentResource;
use App\Http\Resources\Api\V1\InterventionResource;
use App\Http\Resources\Api\V1\PatientResource;
use App\Http\Resources\Api\V1\PatientTaskResource;
use App\Models\Patient;
use App\Models\PatientTask;
use App\Services\AppointmentService;
use App\Services\InterventionService;
use App\Services\PatientService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class PatientController extends ApiController
{
    public function __construct(
        private readonly PatientService $patientService,
        private readonly AppointmentService $appointmentService,
        private readonly InterventionService $interventionService,
    ) {
    }

    public function index(IndexPatientRequest $request): AnonymousResourceCollection
    {
        $filters = $request->validated();

        $patients = Patient::query()
            ->where('company_id', $request->user()->company_id)
            ->when($filters['search'] ?? null, function (Builder $query, string $search): void {
                $query->where(function (Builder $inner) use ($search): void {
                    $inner->where('first_name', 'like', '%'.$search.'%')
                        ->orWhere('last_name', 'like', '%'.$search.'%')
                        ->orWhere('phone', 'like', '%'.$search.'%')
                        ->orWhere('email', 'like', '%'.$search.'%');
                });
            })
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('manual_status', $status))
            ->when(
                $filters['primary_dentist_id'] ?? null,
                fn (Builder $query, int $dentistId) => $query->where('primary_dentist_id', $dentistId)
            )
            ->when(array_key_exists('has_open_tasks', $filters), function (Builder $query) use ($filters): void {
                $method = $filters['has_open_tasks'] ? 'whereHas' : 'whereDoesntHave';
                $query->{$method}('tasks', fn (Builder $taskQuery) => $taskQuery->where('status', PatientTask::STATUS_OPEN));
            })
            ->when(array_key_exists('has_debt', $filters), function (Builder $query) use ($filters): void {
                $method = $filters['has_debt'] ? 'whereHas' : 'whereDoesntHave';
                $query->{$method}('interventions', fn (Builder $interventionQuery) => $interventionQuery
                    ->whereColumn('total_cost', '>', 'paid_amount'));
            })
            ->with('primaryDentist')
            ->withCount([
                'tasks as open_tasks_count' => fn (Builder $query) => $query->where('status', PatientTask::STATUS_OPEN),
            ])
            ->withSum('interventions as total_cost_sum', 'total_cost')
            ->withSum('interventions as paid_amount_sum', 'paid_amount')
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->paginate((int) ($filters['per_page'] ?? 15))
            ->withQueryString();

        return PatientResource::collection($patients);
    }

    public function store(StorePatientRequest $request): PatientResource
    {
        $patient = $this->patientService->create($request->user(), $request->validated());

        return new PatientResource($this->loadPatientForResponse($patient));
    }

    public function show(Request $request, Patient $patient): PatientResource
    {
        Gate::authorize('view', $patient);

        return new PatientResource($this->loadPatientForResponse($patient));
    }

    public function update(UpdatePatientRequest $request, Patient $patient): PatientResource
    {
        Gate::authorize('update', $patient);

        $patient = $this->patientService->update($request->user(), $patient, $request->validated());

        return new PatientResource($this->loadPatientForResponse($patient));
    }

    public function destroy(Patient $patient): JsonResponse
    {
        Gate::authorize('update', $patient);

        $patient->delete();

        return response()->json(status: 204);
    }

    public function updateStatus(UpdatePatientStatusRequest $request, Patient $patient): PatientResource
    {
        Gate::authorize('changeStatus', $patient);

        $validated = $request->validated();
        $patient = $this->patientService->changeManualStatus(
            $request->user(),
            $patient,
            $validated['manual_status'],
            $validated['manual_status_reason'] ?? null
        );

        return new PatientResource($this->loadPatientForResponse($patient));
    }

    public function storeTask(StorePatientTaskRequest $request, Patient $patient): PatientTaskResource
    {
        Gate::authorize('createTask', $patient);

        $task = $this->patientService->addTask($request->user(), $patient, $request->validated());

        return new PatientTaskResource($task->loadMissing(['createdBy', 'assignedTo', 'closedBy']));
    }

    public function completeTask(Request $request, Patient $patient, PatientTask $task): PatientTaskResource
    {
        Gate::authorize('complete', $task);

        $task = $this->patientService->completeTask($request->user(), $task);

        return new PatientTaskResource($task->loadMissing(['createdBy', 'assignedTo', 'closedBy']));
    }

    public function storeAppointment(StoreAppointmentRequest $request, Patient $patient): AppointmentResource
    {
        Gate::authorize('createAppointment', $patient);

        $appointment = $this->appointmentService->schedule($request->user(), $patient, $request->validated());

        return new AppointmentResource($appointment->loadMissing(['patient', 'scheduledBy', 'assignedTo']));
    }

    public function storeIntervention(StoreInterventionRequest $request, Patient $patient): InterventionResource
    {
        Gate::authorize('createIntervention', $patient);

        $intervention = $this->interventionService->record($request->user(), $patient, $request->validated());

        return new InterventionResource($intervention->loadMissing(['patient', 'appointment', 'performedBy']));
    }

    private function loadPatientForResponse(Patient $patient): Patient
    {
        return $patient->loadMissing([
            'primaryDentist',
            'manualStatusChangedBy',
            'appointments.scheduledBy',
            'appointments.assignedTo',
            'interventions.performedBy',
            'tasks.createdBy',
            'tasks.assignedTo',
            'tasks.closedBy',
            'statusLogs.changedBy',
        ])->loadCount([
            'tasks as open_tasks_count' => fn (Builder $query) => $query->where('status', PatientTask::STATUS_OPEN),
        ])->loadSum('interventions as total_cost_sum', 'total_cost')
            ->loadSum('interventions as paid_amount_sum', 'paid_amount');
    }
}
