<?php

namespace App\Http\Controllers;

use App\Http\Requests\Appointment\StoreAppointmentRequest;
use App\Services\AppointmentService;
use App\Services\PatientService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AppointmentController extends Controller
{
    public function __construct(
        private readonly AppointmentService $appointmentService,
        private readonly PatientService $patientService,
    ) {
    }

    public function store(StoreAppointmentRequest $request, int $patientId): RedirectResponse
    {
        $patient = $this->patientService->findForUser($request->user(), $patientId);
        abort_if($patient === null, 404);

        $this->appointmentService->schedule($request->user(), $patient, $request->validated());

        return back()->with('status', 'Termin je uspesno zakazan.');
    }
}

