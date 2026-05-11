<?php

namespace App\Http\Controllers;

use App\Http\Requests\Patient\UpdatePatientStatusRequest;
use App\Services\PatientService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PatientStatusController extends Controller
{
    public function __construct(
        private readonly PatientService $patientService,
    ) {
    }

    public function update(UpdatePatientStatusRequest $request, int $patientId): RedirectResponse
    {
        $patient = $this->patientService->findForUser($request->user(), $patientId);
        abort_if($patient === null, 404);

        $validated = $request->validated();
        $this->patientService->changeManualStatus(
            $request->user(),
            $patient,
            $validated['manual_status'],
            $validated['manual_status_reason'] ?? null
        );

        return back()->with('status', 'Status pacijenta je promenjen.');
    }
}

