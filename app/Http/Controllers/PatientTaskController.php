<?php

namespace App\Http\Controllers;

use App\Http\Requests\Patient\StorePatientTaskRequest;
use App\Models\Patient;
use App\Models\PatientTask;
use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Services\PatientService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class PatientTaskController extends Controller
{
    public function __construct(
        private readonly PatientService $patientService,
        private readonly UserRepositoryInterface $userRepository,
    ) {
    }

    public function create(Request $request, Patient $patient): View
    {
        Gate::authorize('createTask', $patient);

        $dentists = $this->userRepository->forCompanyByRoles((int) $request->user()->company_id, [
            User::ROLE_DENTIST,
            User::ROLE_COMPANY_ADMIN,
        ]);

        return view('patients.tasks.create', [
            'patient' => $patient,
            'dentists' => $dentists,
        ]);
    }

    public function store(StorePatientTaskRequest $request, Patient $patient): RedirectResponse
    {
        Gate::authorize('createTask', $patient);

        $this->patientService->addTask($request->user(), $patient, $request->validated());

        return back()->with('status', 'Aktivna stavka je dodata.');
    }

    public function complete(Request $request, Patient $patient, PatientTask $task): RedirectResponse
    {
        Gate::authorize('complete', $task);

        $this->patientService->completeTask($request->user(), $task);

        return back()->with('status', 'Stavka je oznacena kao zavrsena.');
    }
}

