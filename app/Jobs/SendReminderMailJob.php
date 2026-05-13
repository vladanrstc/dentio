<?php

namespace App\Jobs;

use App\Mail\ReminderMail;
use App\Models\Reminder;
use App\Repositories\Contracts\ReminderRepositoryInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable as QueueableJob;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendReminderMailJob implements ShouldQueue
{
    use QueueableJob;

    public function __construct(
        public readonly int $reminderId,
    ) {
    }

    public function handle(ReminderRepositoryInterface $reminderRepository): void
    {
        $reminder = Reminder::query()->find($this->reminderId);

        if (! $reminder instanceof Reminder || $reminder->status !== Reminder::STATUS_PENDING) {
            return;
        }

        try {
            Mail::to($reminder->recipient_email)->send(new ReminderMail($reminder));
            $reminderRepository->markSent($reminder);
        } catch (Throwable $exception) {
            $reminderRepository->markFailed($reminder, $exception->getMessage());

            throw $exception;
        }
    }
}
