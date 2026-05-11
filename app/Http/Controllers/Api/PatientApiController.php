<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Appointment\StoreAppointmentRequest;
use App\Http\Requests\Intervention\StoreInterventionRequest;
use App\Http\Requests\Patient\StorePatientRequest;
use App\Http\Requests\Patient\StorePatientTaskRequest;
use App\Http\Requests\Patient\UpdatePatientRequest;
use App\Http\Requests\Patient\UpdatePatientStatusRequest;
use App\Http\Resources\AppointmentResource;
use App\Http\Resources\InterventionResource;
use App\Http\Resources\PatientCollection;
use App\Http\Resources\PatientResource;
use App\Http\Resources\PatientTaskResource;
use App\Models\Patient;
use App\Repositories\Contracts\PatientTaskRepositoryInterface;
use App\Services\AppointmentService;
use App\Services\InterventionService;
use App\Services\PatientService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PatientApiController extends Controller
{
    public function __construct(
        private readonly PatientService $patientService,
        private readonly AppointmentService $appointmentService,
        private readonly InterventionService $interventionService,
        private readonly PatientTaskRepositoryInterface $patientTaskRepository,
    ) {}

    public function index(Request $request): PatientCollection
    {
        $search = $request->string('search')->toString();
        $patients = $this->patientService->paginateForUser($request->user(), $search !== '' ? $search : null);

        return new PatientCollection($patients);
    }

    public function store(StorePatientRequest $request): JsonResponse
    {
        $patient = $this->patientService->create($request->user(), $request->validated());

        return (new PatientResource($patient))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, int $patientId): PatientResource
    {
        $patient = $this->patientService->findForUser($request->user(), $patientId, true);
        abort_if($patient === null, 404);

        return new PatientResource($patient);
    }

    public function update(UpdatePatientRequest $request, int $patientId): PatientResource
    {
        $patient = $this->patientService->findForUser($request->user(), $patientId);

        abort_if($patient === null, Response::HTTP_NOT_FOUND);

        $this->patientService->update($request->user(), $patient, $request->validated());

        $patient = $this->patientService->findForUser($request->user(), $patientId, true);

        return new PatientResource($patient);
    }

    public function storeAppointment(StoreAppointmentRequest $request, int $patientId): JsonResponse
    {
        $patient = $this->findPatientOrFail($request, $patientId);

        $appointment = $this->appointmentService->schedule($request->user(), $patient, $request->validated());
        $appointment->loadMissing(['patient', 'assignedTo', 'scheduledBy']);

        return (new AppointmentResource($appointment))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function storeIntervention(StoreInterventionRequest $request, int $patientId): JsonResponse
    {
        $patient = $this->findPatientOrFail($request, $patientId);

        $intervention = $this->interventionService->record($request->user(), $patient, $request->validated());
        $intervention->loadMissing(['performedBy']);

        return (new InterventionResource($intervention))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function storeTask(StorePatientTaskRequest $request, int $patientId): JsonResponse
    {
        $patient = $this->findPatientOrFail($request, $patientId);

        $task = $this->patientService->addTask($request->user(), $patient, $request->validated());
        $task->loadMissing(['assignedTo', 'createdBy', 'closedBy']);

        return (new PatientTaskResource($task))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function completeTask(Request $request, int $patientId, int $taskId): PatientTaskResource
    {
        $patient = $this->findPatientOrFail($request, $patientId);

        $task = $this->patientTaskRepository->findForCompanyPatient((int) $request->user()->company_id, $patient->id, $taskId);
        abort_if($task === null, Response::HTTP_NOT_FOUND);

        $task = $this->patientService->completeTask($request->user(), $task);
        $task->loadMissing(['assignedTo', 'createdBy', 'closedBy']);

        return new PatientTaskResource($task);
    }

    public function updateStatus(UpdatePatientStatusRequest $request, int $patientId): PatientResource
    {
        $patient = $this->findPatientOrFail($request, $patientId);

        $validated = $request->validated();
        $this->patientService->changeManualStatus(
            $request->user(),
            $patient,
            $validated['manual_status'],
            $validated['manual_status_reason'] ?? null
        );

        $patient = $this->patientService->findForUser($request->user(), $patientId, true);

        return new PatientResource($patient);
    }

    private function findPatientOrFail(Request $request, int $patientId): Patient
    {
        $patient = $this->patientService->findForUser($request->user(), $patientId);
        abort_if($patient === null, Response::HTTP_NOT_FOUND);

        return $patient;
    }
}
