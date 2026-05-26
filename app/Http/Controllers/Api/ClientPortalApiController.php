<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AppointmentResource;
use App\Http\Resources\InterventionResource;
use App\Http\Resources\PatientResource;
use App\Http\Resources\PatientTaskResource;
use App\Models\Patient;
use App\Services\Contracts\ClientPortalServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClientPortalApiController extends Controller
{
    public function __construct(
        private readonly ClientPortalServiceInterface $clientPortal,
    ) {}

    public function me(Request $request): PatientResource
    {
        return new PatientResource($this->clientPortal->profile($this->patient($request)));
    }

    public function dashboard(Request $request): JsonResponse
    {
        $patient = $this->clientPortal->profile($this->patient($request));
        $appointments = $this->clientPortal->appointments($patient);
        $interventions = $this->clientPortal->interventions($patient);
        $tasks = $this->clientPortal->tasks($patient);
        $patientData = (new PatientResource($patient))->resolve($request);

        return response()->json([
            'data' => [
                'patient' => $patientData,
                'appointments' => AppointmentResource::collection($appointments)->resolve($request),
                'interventions' => InterventionResource::collection($interventions)->resolve($request),
                'tasks' => PatientTaskResource::collection($tasks)->resolve($request),
                'financials' => $patientData['financials'],
            ],
        ]);
    }

    public function appointments(Request $request): JsonResponse
    {
        return response()->json([
            'data' => AppointmentResource::collection(
                $this->clientPortal->appointments($this->patient($request))
            )->resolve($request),
        ]);
    }

    public function interventions(Request $request): JsonResponse
    {
        return response()->json([
            'data' => InterventionResource::collection(
                $this->clientPortal->interventions($this->patient($request))
            )->resolve($request),
        ]);
    }

    public function tasks(Request $request): JsonResponse
    {
        return response()->json([
            'data' => PatientTaskResource::collection(
                $this->clientPortal->tasks($this->patient($request))
            )->resolve($request),
        ]);
    }

    private function patient(Request $request): Patient
    {
        /** @var Patient $patient */
        $patient = $request->user();

        return $patient;
    }
}
