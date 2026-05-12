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
use App\Models\Intervention;
use App\Models\Patient;
use App\Models\PatientTask;
use App\Models\Reminder;
use App\Repositories\Contracts\PatientTaskRepositoryInterface;
use App\Services\AppointmentService;
use App\Services\InterventionService;
use App\Services\PatientService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

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

    public function destroy(Request $request, int $patientId): JsonResponse
    {
        $patient = $this->patientService->findForUser($request->user(), $patientId);

        abort_if($patient === null, Response::HTTP_NOT_FOUND);

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

        return response()->json([
            'message' => 'Pacijent je obrisan.',
        ]);
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

    public function cancelAppointment(Request $request, int $appointmentId): AppointmentResource
    {
        $validated = $request->validate([
            'cancel_reason' => ['nullable', 'string'],
        ]);

        $appointment = Appointment::query()
            ->where('company_id', $request->user()->company_id)
            ->whereKey($appointmentId)
            ->first();

        abort_if($appointment === null, Response::HTTP_NOT_FOUND);

        $appointment = $this->appointmentService->cancel(
            $request->user(),
            $appointment,
            $validated['cancel_reason'] ?? null,
        );
        $appointment->loadMissing(['patient', 'assignedTo', 'scheduledBy']);

        return new AppointmentResource($appointment);
    }

    private function findPatientOrFail(Request $request, int $patientId): Patient
    {
        $patient = $this->patientService->findForUser($request->user(), $patientId);
        abort_if($patient === null, Response::HTTP_NOT_FOUND);

        return $patient;
    }
}
