<?php

namespace App\Services;

use App\Jobs\SendReminderMailJob;
use App\Repositories\Contracts\ReminderRepositoryInterface;
use Illuminate\Support\Carbon;

class ReminderDispatchService
{
    public function __construct(
        private readonly ReminderRepositoryInterface $reminderRepository,
    ) {
    }

    public function sendDueReminders(int $limit = 100): int
    {
        $reminders = $this->reminderRepository->duePending(Carbon::now(), $limit);
        $queued = 0;

        foreach ($reminders as $reminder) {
            SendReminderMailJob::dispatch($reminder->id);
            $queued++;
        }

        return $queued;
    }
}

