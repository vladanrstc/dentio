<?php

namespace App\Http\Controllers;

use App\Http\Requests\Appointment\StoreAppointmentRequest;
use App\Models\Patient;
use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Services\AppointmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class AppointmentController extends Controller
{
    public function __construct(
        private readonly AppointmentService $appointmentService,
        private readonly UserRepositoryInterface $userRepository,
    ) {
    }

    public function create(Request $request, Patient $patient): View
    {
        Gate::authorize('createAppointment', $patient);

        $dentists = $this->userRepository->forCompanyByRoles((int) $request->user()->company_id, [
            User::ROLE_DENTIST,
            User::ROLE_COMPANY_ADMIN,
        ]);

        return view('patients.appointments.create', [
            'patient' => $patient,
            'dentists' => $dentists,
        ]);
    }

    public function store(StoreAppointmentRequest $request, Patient $patient): RedirectResponse
    {
        Gate::authorize('createAppointment', $patient);

        $this->appointmentService->schedule($request->user(), $patient, $request->validated());

        return back()->with('status', 'Termin je uspesno zakazan.');
    }
}

