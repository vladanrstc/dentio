<?php

namespace App\Repositories\Eloquent;

use App\Models\Reminder;
use App\Repositories\Contracts\ReminderRepositoryInterface;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class EloquentReminderRepository implements ReminderRepositoryInterface
{
    public function createMany(array $rows): void
    {
        if ($rows === []) {
            return;
        }

        Reminder::query()->insert($rows);
    }

    public function duePending(CarbonInterface $at, int $limit = 100): Collection
    {
        return Reminder::query()
            ->where('status', Reminder::STATUS_PENDING)
            ->where('remind_at', '<=', $at)
            ->orderBy('remind_at')
            ->limit($limit)
            ->get();
    }

    public function countDueForCompany(int $companyId): int
    {
        return Reminder::query()
            ->where('company_id', $companyId)
            ->where('status', Reminder::STATUS_PENDING)
            ->where('remind_at', '<=', Carbon::now())
            ->count();
    }

    public function markSent(Reminder $reminder): Reminder
    {
        $reminder->status = Reminder::STATUS_SENT;
        $reminder->sent_at = Carbon::now();
        $reminder->error_message = null;
        $reminder->save();

        return $reminder;
    }

    public function markFailed(Reminder $reminder, string $error): Reminder
    {
        $reminder->status = Reminder::STATUS_FAILED;
        $reminder->error_message = $error;
        $reminder->save();

        return $reminder;
    }
}

