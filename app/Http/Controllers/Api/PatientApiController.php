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
use App\Models\Appointment;
use App\Models\Patient;
use App\Models\PatientTask;
use App\Repositories\Contracts\PatientTaskRepositoryInterface;
use App\Services\Contracts\AppointmentServiceInterface;
use App\Services\Contracts\InterventionServiceInterface;
use App\Services\Contracts\PatientServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PatientApiController extends Controller
{
    public function __construct(
        private readonly PatientServiceInterface $patientService,
        private readonly AppointmentServiceInterface $appointmentService,
        private readonly InterventionServiceInterface $interventionService,
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

    public function show(Request $request, Patient $patient): PatientResource
    {
        $patient = $this->patientService->findForUser($request->user(), $patient->id, true);
        abort_if($patient === null, Response::HTTP_NOT_FOUND);

        return new PatientResource($patient);
    }

    public function update(UpdatePatientRequest $request, Patient $patient): PatientResource
    {
        $this->patientService->update($request->user(), $patient, $request->validated());

        $patient = $this->patientService->findForUser($request->user(), $patient->id, true);

        return new PatientResource($patient);
    }

    public function destroy(Request $request, Patient $patient): JsonResponse
    {
        $this->patientService->delete($request->user(), $patient);

        return response()->json([
            'message' => __('errors.patient_deleted'),
        ]);
    }

    public function storeAppointment(StoreAppointmentRequest $request, Patient $patient): JsonResponse
    {
        $patient = $this->patientService->assertAccessible($request->user(), $patient);

        $appointment = $this->appointmentService->schedule($request->user(), $patient, $request->validated());
        $appointment->loadMissing(['patient', 'assignedTo', 'scheduledBy']);

        return (new AppointmentResource($appointment))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function storeIntervention(StoreInterventionRequest $request, Patient $patient): JsonResponse
    {
        $patient = $this->patientService->assertAccessible($request->user(), $patient);

        $intervention = $this->interventionService->record($request->user(), $patient, $request->validated());
        $intervention->loadMissing(['performedBy']);

        return (new InterventionResource($intervention))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function storeTask(StorePatientTaskRequest $request, Patient $patient): JsonResponse
    {
        $patient = $this->patientService->assertAccessible($request->user(), $patient);

        $task = $this->patientService->addTask($request->user(), $patient, $request->validated());
        $task->loadMissing(['assignedTo', 'createdBy', 'closedBy']);

        return (new PatientTaskResource($task))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function completeTask(Request $request, Patient $patient, PatientTask $task): PatientTaskResource
    {
        $patient = $this->patientService->assertAccessible($request->user(), $patient);

        $task = $this->patientTaskRepository->findForCompanyPatient((int) $request->user()->company_id, $patient->id, $task->id);
        abort_if($task === null, Response::HTTP_NOT_FOUND);

        $task = $this->patientService->completeTask($request->user(), $task);
        $task->loadMissing(['assignedTo', 'createdBy', 'closedBy']);

        return new PatientTaskResource($task);
    }

    public function updateStatus(UpdatePatientStatusRequest $request, Patient $patient): PatientResource
    {
        $patient = $this->patientService->assertAccessible($request->user(), $patient);

        $validated = $request->validated();
        $this->patientService->changeManualStatus(
            $request->user(),
            $patient,
            $validated['manual_status'],
            $validated['manual_status_reason'] ?? null
        );

        $patient = $this->patientService->findForUser($request->user(), $patient->id, true);

        return new PatientResource($patient);
    }

    public function cancelAppointment(Request $request, Appointment $appointment): AppointmentResource
    {
        $validated = $request->validate([
            'cancel_reason' => ['nullable', 'string'],
        ]);

        $appointment = $this->appointmentService->cancel(
            $request->user(),
            $appointment,
            $validated['cancel_reason'] ?? null,
        );
        $appointment->loadMissing(['patient', 'assignedTo', 'scheduledBy']);

        return new AppointmentResource($appointment);
    }
}
