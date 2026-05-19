<?php

namespace App\Http\Controllers;

use App\Http\Requests\Intervention\StoreInterventionRequest;
use App\Models\Patient;
use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Services\InterventionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class InterventionController extends Controller
{
    public function __construct(
        private readonly InterventionService $interventionService,
        private readonly UserRepositoryInterface $userRepository,
    ) {
    }

    public function create(Request $request, Patient $patient): View
    {
        Gate::authorize('createIntervention', $patient);
        $patient->loadMissing(['appointments']);

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

    public function store(StoreInterventionRequest $request, Patient $patient): RedirectResponse
    {
        Gate::authorize('createIntervention', $patient);

        $this->interventionService->record($request->user(), $patient, $request->validated());

        return back()->with('status', 'Intervencija je sacuvana.');
    }
}

