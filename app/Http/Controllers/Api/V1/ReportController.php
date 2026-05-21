<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\Report\AppointmentsReportRequest;
use App\Http\Requests\Api\V1\Report\InterventionsFinancialReportRequest;
use App\Http\Requests\Api\V1\Report\PatientsReportRequest;
use Illuminate\Http\JsonResponse;

class ReportController extends ApiController
{
    public function patients(PatientsReportRequest $request): JsonResponse
    {
        $request->validated();

        return $this->notImplemented('patients');
    }

    public function appointments(AppointmentsReportRequest $request): JsonResponse
    {
        $request->validated();

        return $this->notImplemented('appointments');
    }

    public function interventionsFinancial(InterventionsFinancialReportRequest $request): JsonResponse
    {
        $request->validated();

        return $this->notImplemented('interventions-financial');
    }

    private function notImplemented(string $report): JsonResponse
    {
        return $this->respond([
            'message' => 'Report export is not implemented yet.',
            'report' => $report,
            'todo' => true,
        ], 501);
    }
}
