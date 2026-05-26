<?php

namespace App\Services\Reports;

use App\Enums\ReportFrequency;
use App\Models\ReportSubscription;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class ReportSubscriptionService
{
    /**
     * @param  list<string>  $allowedReports
     * @return Collection<int, ReportSubscription>
     */
    public function list(User $user, array $allowedReports)
    {
        return ReportSubscription::query()
            ->where('user_id', $user->id)
            ->whereIn('report_key', $allowedReports)
            ->orderBy('report_key')
            ->get();
    }

    /**
     * @param  list<string>  $allowedReports
     * @param  array<string, mixed>  $data
     */
    public function upsert(User $user, string $reportKey, array $allowedReports, array $data): ReportSubscription
    {
        if (! in_array($reportKey, $allowedReports, true)) {
            throw ValidationException::withMessages([
                'report_key' => [__('errors.report_not_allowed')],
            ]);
        }

        $frequency = $data['frequency'];
        $format = $data['format'];

        return ReportSubscription::query()->updateOrCreate(
            [
                'user_id' => $user->id,
                'report_key' => $reportKey,
            ],
            [
                'company_id' => $user->company_id,
                'frequency' => $frequency,
                'format' => $format,
                'filters' => $data['filters'] ?? [],
                'next_run_at' => $this->nextRunAt($frequency),
            ],
        );
    }

    public function nextRunAt(string $frequency): ?Carbon
    {
        return match ($frequency) {
            ReportFrequency::DAILY->value => Carbon::now()->addDay(),
            ReportFrequency::WEEKLY->value => Carbon::now()->addWeek(),
            ReportFrequency::MONTHLY->value => Carbon::now()->addMonth(),
            default => null,
        };
    }
}
