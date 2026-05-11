<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Patient\StorePatientRequest;
use App\Http\Resources\PatientCollection;
use App\Http\Resources\PatientResource;
use App\Services\PatientService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PatientApiController extends Controller
{
    public function __construct(
        private readonly PatientService $patientService,
    ) {
    }

    public function index(Request $request): PatientCollection
    {
        $search = $request->string('search')->toString();
        $patients = $this->patientService->paginateForUser($request->user(), $search !== '' ? $search : null);

        return new PatientCollection($patients);
    }

    public function store(StorePatientRequest $request): JsonResponse
    {
        $patient = $this->patientService->create($request->user(), $request->validated());

        return (new PatientResource($patient))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, int $patientId): PatientResource
    {
        $patient = $this->patientService->findForUser($request->user(), $patientId, true);
        abort_if($patient === null, 404);

        return new PatientResource($patient);
    }
}