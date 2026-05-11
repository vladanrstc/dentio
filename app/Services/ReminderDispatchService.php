<?php

namespace App\Services;

use App\Mail\ReminderMail;
use App\Repositories\Contracts\ReminderRepositoryInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Throwable;

class ReminderDispatchService
{
    public function __construct(
        private readonly ReminderRepositoryInterface $reminderRepository,
    ) {
    }

    public function sendDueReminders(int $limit = 100): int
    {
        $reminders = $this->reminderRepository->duePending(Carbon::now(), $limit);
        $sent = 0;

        foreach ($reminders as $reminder) {
            try {
                Mail::to($reminder->recipient_email)->send(new ReminderMail($reminder));
                $this->reminderRepository->markSent($reminder);
                $sent++;
            } catch (Throwable $exception) {
                $this->reminderRepository->markFailed($reminder, $exception->getMessage());
            }
        }

        return $sent;
    }
}

