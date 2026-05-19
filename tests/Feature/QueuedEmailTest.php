<?php

namespace Tests\Feature;

use App\Jobs\SendReminderMailJob;
use App\Mail\InviteMail;
use App\Mail\ReminderMail;
use App\Models\Company;
use App\Models\Reminder;
use App\Models\User;
use App\Repositories\Contracts\ReminderRepositoryInterface;
use App\Services\InviteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class QueuedEmailTest extends TestCase
{
    use RefreshDatabase;

    public function test_invite_emails_are_queued_not_sent_synchronously(): void
    {
        Mail::fake();

        /** @var InviteService $service */
        $service = app(InviteService::class);
        $ownerInvite = $service->sendOwnerInvite('owner@example.com');

        $company = Company::query()->create([
            'name' => 'Dentio Demo',
            'address' => 'Demo Street',
            'email' => 'demo@example.com',
        ]);
        $inviter = User::factory()->create([
            'company_id' => $company->id,
            'role' => User::ROLE_COMPANY_ADMIN,
        ]);
        $staffInvite = $service->sendStaffInvite($inviter, 'staff@example.com', User::ROLE_DENTIST);

        Mail::assertQueued(InviteMail::class, 2);
        Mail::assertQueued(InviteMail::class, fn (InviteMail $mail) => $mail->invite->is($ownerInvite));
        Mail::assertQueued(InviteMail::class, fn (InviteMail $mail) => $mail->invite->is($staffInvite));
        Mail::assertNothingSent();
    }

    public function test_reminders_send_due_dispatches_only_due_pending_reminder_jobs(): void
    {
        Queue::fake();

        $company = $this->company();
        $due = $this->reminder($company, [
            'recipient_email' => 'due@example.com',
            'remind_at' => Carbon::now()->subMinute(),
        ]);
        $future = $this->reminder($company, [
            'recipient_email' => 'future@example.com',
            'remind_at' => Carbon::now()->addHour(),
        ]);
        $sent = $this->reminder($company, [
            'recipient_email' => 'sent@example.com',
            'remind_at' => Carbon::now()->subMinute(),
            'status' => Reminder::STATUS_SENT,
            'sent_at' => Carbon::now()->subMinute(),
        ]);

        $this->artisan('reminders:send-due')
            ->expectsOutput('Podsetnika dodatih u red za slanje: 1')
            ->assertExitCode(0);

        Queue::assertPushed(SendReminderMailJob::class, 1);
        Queue::assertPushed(SendReminderMailJob::class, fn (SendReminderMailJob $job) => $job->reminderId === $due->id);
        Queue::assertNotPushed(SendReminderMailJob::class, fn (SendReminderMailJob $job) => $job->reminderId === $future->id);
        Queue::assertNotPushed(SendReminderMailJob::class, fn (SendReminderMailJob $job) => $job->reminderId === $sent->id);

        $this->assertDatabaseHas('reminders', [
            'id' => $due->id,
            'status' => Reminder::STATUS_PENDING,
            'sent_at' => null,
        ]);
    }

    public function test_send_reminder_mail_job_sends_mail_and_marks_reminder_sent_after_success(): void
    {
        Mail::fake();

        $company = $this->company();
        $reminder = $this->reminder($company, [
            'recipient_email' => 'patient@example.com',
            'remind_at' => Carbon::now()->subMinute(),
        ]);

        $this->assertDatabaseHas('reminders', [
            'id' => $reminder->id,
            'status' => Reminder::STATUS_PENDING,
            'sent_at' => null,
        ]);

        $job = new SendReminderMailJob($reminder->id);
        $job->handle(app(ReminderRepositoryInterface::class));

        Mail::assertSent(ReminderMail::class, fn (ReminderMail $mail) => $mail->reminder->is($reminder));
        $this->assertDatabaseHas('reminders', [
            'id' => $reminder->id,
            'status' => Reminder::STATUS_SENT,
            'error_message' => null,
        ]);
        $this->assertNotNull($reminder->fresh()->sent_at);
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function reminder(Company $company, array $overrides = []): Reminder
    {
        return Reminder::query()->create(array_merge([
            'company_id' => $company->id,
            'recipient_email' => 'reminder@example.com',
            'recipient_type' => Reminder::TYPE_PATIENT,
            'remind_at' => Carbon::now()->subMinute(),
            'subject' => 'Reminder',
            'body' => 'Reminder body',
            'status' => Reminder::STATUS_PENDING,
        ], $overrides));
    }

    private function company(): Company
    {
        return Company::query()->create([
            'name' => 'Dentio Demo',
            'address' => 'Demo Street',
            'email' => 'demo@example.com',
        ]);
    }
}
