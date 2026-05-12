<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PatientCollection;
use App\Http\Resources\PatientResource;
use App\Services\PatientService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

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

    public function show(Request $request, int $patientId): PatientResource
    {
        $patient = $this->patientService->findForUser($request->user(), $patientId, true);
        abort_if($patient === null, 404);
        Gate::authorize('view', $patient);

        return new PatientResource($patient);
    }
}

