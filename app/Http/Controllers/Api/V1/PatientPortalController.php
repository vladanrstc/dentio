<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\Api\V1\PatientPortalResource;
use App\Models\Patient;
use Illuminate\Http\Request;

class PatientPortalController extends ApiController
{
    public function show(Request $request): PatientPortalResource
    {
        $patient = Patient::query()
            ->where('user_id', $request->user()->id)
            ->with([
                'primaryDentist',
                'appointments.assignedTo',
                'appointments.scheduledBy',
                'interventions.performedBy',
                'interventions.appointment',
                'tasks.assignedTo',
                'tasks.createdBy',
                'tasks.closedBy',
            ])
            ->firstOrFail();

        return new PatientPortalResource($patient);
    }
}
