<?php

namespace App\Http\Controllers;

use App\Http\Requests\Patient\StorePatientRequest;
use App\Http\Requests\Patient\UpdatePatientRequest;
use App\Models\Patient;
use App\Models\PatientTask;
use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Services\PatientService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class PatientController extends Controller
{
    public function __construct(
        private readonly PatientService $patientService,
        private readonly UserRepositoryInterface $userRepository,
    ) {
    }

    public function index(Request $request): View
    {
        $search = $request->string('search')->toString();
        $patients = $this->patientService->paginateForUser($request->user(), $search !== '' ? $search : null);
        $dentists = $this->userRepository->forCompanyByRoles((int) $request->user()->company_id, [
            User::ROLE_DENTIST,
            User::ROLE_COMPANY_ADMIN,
        ]);

        return view('patients.index', [
            'patients' => $patients,
            'search' => $search,
            'dentists' => $dentists,
        ]);
    }

    public function create(Request $request): View
    {
        $dentists = $this->userRepository->forCompanyByRoles((int) $request->user()->company_id, [
            User::ROLE_DENTIST,
            User::ROLE_COMPANY_ADMIN,
        ]);

        return view('patients.create', [
            'dentists' => $dentists,
        ]);
    }

    public function store(StorePatientRequest $request): RedirectResponse
    {
        $patient = $this->patientService->create($request->user(), $request->validated());

        return redirect()->route('patients.show', $patient->id)
            ->with('status', 'Pacijent je uspesno dodat.');
    }

    public function show(Request $request, Patient $patient): View
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

        $dentists = $this->userRepository->forCompanyByRoles((int) $request->user()->company_id, [
            User::ROLE_DENTIST,
            User::ROLE_COMPANY_ADMIN,
        ]);

        return view('patients.show', [
            'patient' => $patient,
            'dentists' => $dentists,
        ]);
    }

    public function edit(Request $request, Patient $patient): View
    {
        Gate::authorize('update', $patient);

        $dentists = $this->userRepository->forCompanyByRoles((int) $request->user()->company_id, [
            User::ROLE_DENTIST,
            User::ROLE_COMPANY_ADMIN,
        ]);

        return view('patients.edit', [
            'patient' => $patient,
            'dentists' => $dentists,
        ]);
    }

    public function update(UpdatePatientRequest $request, Patient $patient): RedirectResponse
    {
        Gate::authorize('update', $patient);

        $this->patientService->update($request->user(), $patient, $request->validated());

        return back()->with('status', 'Podaci o pacijentu su azurirani.');
    }
}
