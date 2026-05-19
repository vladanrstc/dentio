<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Intervention;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

class DailySuperadminReportService
{
    /**
     * @return array{
     *     date: string,
     *     appointments_count: int,
     *     checkups_count: int,
     *     total_outstanding_debt: float,
     *     interventions: list<array<string, mixed>>,
     *     csv: string,
     *     filename: string
     * }
     */
    public function buildForPreviousDay(?CarbonInterface $today = null): array
    {
        $reportDate = Carbon::instance($today ?? Carbon::now())->subDay()->toDateString();
        $start = Carbon::parse($reportDate)->startOfDay();
        $end = Carbon::parse($reportDate)->endOfDay();

        $appointmentsCount = Appointment::query()
            ->whereBetween('starts_at', [$start, $end])
            ->count();

        $checkupsCount = Appointment::query()
            ->where('type', Appointment::TYPE_CHECKUP)
            ->whereBetween('starts_at', [$start, $end])
            ->count();

        $interventions = Intervention::query()
            ->with(['company', 'patient', 'performedBy'])
            ->whereDate('intervention_date', $reportDate)
            ->orderBy('intervention_date')
            ->orderBy('id')
            ->get();

        $interventionRows = $interventions
            ->map(function (Intervention $intervention): array {
                $outstanding = $intervention->outstandingAmount();

                return [
                    'company' => $intervention->company?->name ?? '',
                    'patient' => $intervention->patient?->fullName() ?? '',
                    'performed_by' => $intervention->performedBy?->fullName() ?? '',
                    'title' => $intervention->title,
                    'description' => $intervention->description ?? '',
                    'next_step' => $intervention->next_step ?? '',
                    'total_cost' => (float) $intervention->total_cost,
                    'paid_amount' => (float) $intervention->paid_amount,
                    'outstanding_amount' => $outstanding,
                ];
            })
            ->values()
            ->all();

        $totalOutstandingDebt = array_sum(array_column($interventionRows, 'outstanding_amount'));

        $report = [
            'date' => $reportDate,
            'appointments_count' => $appointmentsCount,
            'checkups_count' => $checkupsCount,
            'total_outstanding_debt' => (float) $totalOutstandingDebt,
            'interventions' => $interventionRows,
            'filename' => 'daily-superadmin-report-'.$reportDate.'.csv',
        ];
        $report['csv'] = $this->toCsv($report);

        return $report;
    }

    /**
     * @param array<string, mixed> $report
     */
    private function toCsv(array $report): string
    {
        $handle = fopen('php://temp', 'r+');

        fputcsv($handle, ['Daily Superadmin Report']);
        fputcsv($handle, ['Date', $report['date']]);
        fputcsv($handle, ['Appointments', $report['appointments_count']]);
        fputcsv($handle, ['Checkups', $report['checkups_count']]);
        fputcsv($handle, ['Total outstanding debt', number_format((float) $report['total_outstanding_debt'], 2, '.', '')]);
        fputcsv($handle, []);
        fputcsv($handle, [
            'Company',
            'Patient',
            'Performed by',
            'Title',
            'Description',
            'Next step',
            'Total cost',
            'Paid amount',
            'Outstanding amount',
        ]);

        foreach ($report['interventions'] as $intervention) {
            fputcsv($handle, [
                $intervention['company'],
                $intervention['patient'],
                $intervention['performed_by'],
                $intervention['title'],
                $intervention['description'],
                $intervention['next_step'],
                number_format((float) $intervention['total_cost'], 2, '.', ''),
                number_format((float) $intervention['paid_amount'], 2, '.', ''),
                number_format((float) $intervention['outstanding_amount'], 2, '.', ''),
            ]);
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return $csv === false ? '' : $csv;
    }
}
