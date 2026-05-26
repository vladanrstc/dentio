<?php

namespace App\Services\Reports;

use App\Mail\ReportMail;
use App\Models\ReportSubscription;
use App\Services\Contracts\ReportServiceInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;

class ReportDispatchService
{
    public function __construct(
        private readonly ReportServiceInterface $reportsService,
        private readonly ReportExportService $exportService,
        private readonly ReportSubscriptionService $subscriptionService,
    ) {}

    public function sendDue(): int
    {
        $sent = 0;

        ReportSubscription::query()
            ->with(['user', 'company'])
            ->where('frequency', '!=', ReportSubscription::FREQUENCY_OFF)
            ->whereNotNull('next_run_at')
            ->where('next_run_at', '<=', Carbon::now())
            ->orderBy('next_run_at')
            ->get()
            ->each(function (ReportSubscription $subscription) use (&$sent): void {
                if ($subscription->user === null || $subscription->user->email === null) {
                    return;
                }

                if ($subscription->report_key !== 'companies' && $subscription->company === null) {
                    return;
                }

                $report = $this->reportsService->forSubscription($subscription, $subscription->filters ?? []);
                $export = $this->exportService->content($report['headers'], $report['rows'], $subscription->format);
                $filename = $report['filename'].'.'.$export['extension'];

                Mail::to($subscription->user->email)->send(new ReportMail(
                    $subscription->report_key,
                    $filename,
                    $export['content'],
                    $export['mime'],
                ));

                $subscription->last_sent_at = Carbon::now();
                $subscription->next_run_at = $this->subscriptionService->nextRunAt($subscription->frequency);
                $subscription->save();
                $sent++;
            });

        return $sent;
    }
}
