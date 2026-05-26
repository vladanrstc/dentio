<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Payment\StoreAppointmentPaymentSessionRequest;
use App\Http\Requests\Payment\StorePatientPaymentRequest;
use App\Http\Resources\PatientPaymentResource;
use App\Models\Appointment;
use App\Services\Contracts\StripePaymentServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class CompanyPaymentApiController extends Controller
{
    public function __construct(
        private readonly StripePaymentServiceInterface $payments,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $patientId = $request->filled('patient_id') ? $request->integer('patient_id') : null;

        return PatientPaymentResource::collection(
            $this->payments->listForCompany($request->user(), $patientId)
        )->response();
    }

    public function store(StorePatientPaymentRequest $request): JsonResponse
    {
        $payment = $this->payments->createPatientPayment($request->user(), $request->validated());

        return (new PatientPaymentResource($payment))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function appointmentSession(StoreAppointmentPaymentSessionRequest $request, Appointment $appointment): JsonResponse
    {
        $payment = $this->payments->createAppointmentPaymentSession(
            $request->user(),
            $appointment,
            $request->validated(),
        );

        return (new PatientPaymentResource($payment))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }
}
