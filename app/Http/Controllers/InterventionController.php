<?php

namespace App\Http\Controllers;

use App\Http\Requests\Intervention\StoreInterventionRequest;
use App\Services\InterventionService;
use App\Services\PatientService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class InterventionController extends Controller
{
    public function __construct(
        private readonly InterventionService $interventionService,
        private readonly PatientService $patientService,
    ) {
    }

    public function store(StoreInterventionRequest $request, int $patientId): RedirectResponse
    {
        $patient = $this->patientService->findForUser($request->user(), $patientId);
        abort_if($patient === null, 404);

        $this->interventionService->record($request->user(), $patient, $request->validated());

        return back()->with('status', 'Intervencija je sacuvana.');
    }
}

