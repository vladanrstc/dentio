<?php

namespace App\Http\Controllers;

use App\Http\Requests\Intervention\StoreInterventionRequest;
use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Services\InterventionService;
use App\Services\PatientService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class InterventionController extends Controller
{
    public function __construct(
        private readonly InterventionService $interventionService,
        private readonly PatientService $patientService,
        private readonly UserRepositoryInterface $userRepository,
    ) {
    }

    public function create(Request $request, int $patientId): View
    {
        $patient = $this->patientService->findForUser($request->user(), $patientId, true);
        abort_if($patient === null, 404);
        Gate::authorize('createIntervention', $patient);

        $dentists = $this->userRepository->forCompanyByRoles((int) $request->user()->company_id, [
            User::ROLE_DENTIST,
            User::ROLE_COMPANY_ADMIN,
        ]);
        $appointments = $patient->appointments->sortByDesc('starts_at');

        return view('patients.interventions.create', [
            'patient' => $patient,
            'dentists' => $dentists,
            'appointments' => $appointments,
        ]);
    }

    public function store(StoreInterventionRequest $request, int $patientId): RedirectResponse
    {
        $patient = $this->patientService->findForUser($request->user(), $patientId);
        abort_if($patient === null, 404);
        Gate::authorize('createIntervention', $patient);

        $this->interventionService->record($request->user(), $patient, $request->validated());

        return back()->with('status', 'Intervencija je sacuvana.');
    }
}

