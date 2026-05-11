<?php

namespace App\Http\Controllers;

use App\Http\Requests\Patient\StorePatientTaskRequest;
use App\Models\User;
use App\Repositories\Contracts\PatientTaskRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Services\PatientService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PatientTaskController extends Controller
{
    public function __construct(
        private readonly PatientService $patientService,
        private readonly PatientTaskRepositoryInterface $patientTaskRepository,
        private readonly UserRepositoryInterface $userRepository,
    ) {
    }

    public function create(Request $request, int $patientId): View
    {
        $patient = $this->patientService->findForUser($request->user(), $patientId);
        abort_if($patient === null, 404);

        $dentists = $this->userRepository->forCompanyByRoles((int) $request->user()->company_id, [
            User::ROLE_DENTIST,
            User::ROLE_COMPANY_ADMIN,
        ]);

        return view('patients.tasks.create', [
            'patient' => $patient,
            'dentists' => $dentists,
        ]);
    }

    public function store(StorePatientTaskRequest $request, int $patientId): RedirectResponse
    {
        $patient = $this->patientService->findForUser($request->user(), $patientId);
        abort_if($patient === null, 404);

        $this->patientService->addTask($request->user(), $patient, $request->validated());

        return back()->with('status', 'Aktivna stavka je dodata.');
    }

    public function complete(Request $request, int $patientId, int $taskId): RedirectResponse
    {
        $patient = $this->patientService->findForUser($request->user(), $patientId);
        abort_if($patient === null, 404);

        $task = $this->patientTaskRepository->findForCompanyPatient((int) $request->user()->company_id, $patient->id, $taskId);
        abort_if($task === null, 404);

        $this->patientService->completeTask($request->user(), $task);

        return back()->with('status', 'Stavka je oznacena kao zavrsena.');
    }
}

