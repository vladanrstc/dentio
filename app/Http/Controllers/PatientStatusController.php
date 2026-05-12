<?php

namespace App\Http\Controllers;

use App\Http\Requests\Patient\UpdatePatientStatusRequest;
use App\Services\PatientService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class PatientStatusController extends Controller
{
    public function __construct(
        private readonly PatientService $patientService,
    ) {
    }

    public function edit(Request $request, int $patientId): View
    {
        $patient = $this->patientService->findForUser($request->user(), $patientId);
        abort_if($patient === null, 404);
        Gate::authorize('changeStatus', $patient);

        return view('patients.status.edit', [
            'patient' => $patient,
        ]);
    }

    public function update(UpdatePatientStatusRequest $request, int $patientId): RedirectResponse
    {
        $patient = $this->patientService->findForUser($request->user(), $patientId);
        abort_if($patient === null, 404);
        Gate::authorize('changeStatus', $patient);

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

