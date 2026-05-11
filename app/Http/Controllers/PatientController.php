<?php

namespace App\Http\Controllers;

use App\Http\Requests\Patient\StorePatientRequest;
use App\Http\Requests\Patient\UpdatePatientRequest;
use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Services\PatientService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

    public function show(Request $request, int $patientId): View
    {
        $patient = $this->patientService->findForUser($request->user(), $patientId, true);
        abort_if($patient === null, 404);

        $dentists = $this->userRepository->forCompanyByRoles((int) $request->user()->company_id, [
            User::ROLE_DENTIST,
            User::ROLE_COMPANY_ADMIN,
        ]);

        return view('patients.show', [
            'patient' => $patient,
            'dentists' => $dentists,
        ]);
    }

    public function edit(Request $request, int $patientId): View
    {
        $patient = $this->patientService->findForUser($request->user(), $patientId);
        abort_if($patient === null, 404);

        $dentists = $this->userRepository->forCompanyByRoles((int) $request->user()->company_id, [
            User::ROLE_DENTIST,
            User::ROLE_COMPANY_ADMIN,
        ]);

        return view('patients.edit', [
            'patient' => $patient,
            'dentists' => $dentists,
        ]);
    }

    public function update(UpdatePatientRequest $request, int $patientId): RedirectResponse
    {
        $patient = $this->patientService->findForUser($request->user(), $patientId);
        abort_if($patient === null, 404);

        $this->patientService->update($request->user(), $patient, $request->validated());

        return back()->with('status', 'Podaci o pacijentu su azurirani.');
    }
}
