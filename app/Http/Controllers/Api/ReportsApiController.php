<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Report\UpdateReportSubscriptionRequest;
use App\Http\Resources\ReportSubscriptionResource;
use App\Services\Contracts\ReportServiceInterface;
use App\Services\Reports\ReportExportService;
use App\Services\Reports\ReportsService;
use App\Services\Reports\ReportSubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class ReportsApiController extends Controller
{
    public function __construct(
        private readonly ReportServiceInterface $reportsService,
        private readonly ReportExportService $exportService,
        private readonly ReportSubscriptionService $subscriptionService,
    ) {}

    public function patients(Request $request): Response
    {
        $report = $this->reportsService->patients((int) $request->user()->company_id, $request);

        return $this->export($report, $request);
    }

    public function appointments(Request $request): Response
    {
        $report = $this->reportsService->appointments((int) $request->user()->company_id, $request);

        return $this->export($report, $request);
    }

    public function interventionsFinancial(Request $request): Response
    {
        $report = $this->reportsService->interventionsFinancial((int) $request->user()->company_id, $request);

        return $this->export($report, $request);
    }

    public function adminCompanies(Request $request): Response
    {
        $report = $this->reportsService->adminCompanies();

        return $this->export($report, $request);
    }

    public function companySubscriptions(Request $request): AnonymousResourceCollection
    {
        return ReportSubscriptionResource::collection(
            $this->subscriptionService->list($request->user(), ReportsService::COMPANY_REPORTS)
        );
    }

    public function updateCompanySubscription(UpdateReportSubscriptionRequest $request, string $reportKey): JsonResponse
    {
        $subscription = $this->subscriptionService->upsert(
            $request->user(),
            $reportKey,
            ReportsService::COMPANY_REPORTS,
            $request->validated(),
        );

        return (new ReportSubscriptionResource($subscription))
            ->response()
            ->setStatusCode(200);
    }

    public function adminSubscriptions(Request $request): AnonymousResourceCollection
    {
        return ReportSubscriptionResource::collection(
            $this->subscriptionService->list($request->user(), ReportsService::ADMIN_REPORTS)
        );
    }

    public function updateAdminSubscription(UpdateReportSubscriptionRequest $request, string $reportKey): JsonResponse
    {
        $subscription = $this->subscriptionService->upsert(
            $request->user(),
            $reportKey,
            ReportsService::ADMIN_REPORTS,
            $request->validated(),
        );

        return (new ReportSubscriptionResource($subscription))
            ->response()
            ->setStatusCode(200);
    }

    /**
     * @param  array{filename: string, headers: list<string>, rows: list<list<scalar|null>>}  $report
     */
    private function export(array $report, Request $request): Response
    {
        return $this->exportService->export(
            $report['filename'],
            $report['headers'],
            $report['rows'],
            $request->string('format', 'csv')->toString(),
        );
    }
}
