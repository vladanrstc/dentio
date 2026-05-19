<?php

namespace Tests\Feature\Reminders;

use App\Mail\ReminderMail;
use App\Models\Reminder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ReminderDispatchTest extends TestCase
{
    use RefreshDatabase;

    public function test_due_pending_reminder_is_sent_and_marked_as_sent(): void
    {
        Mail::fake();
        $reminder = Reminder::factory()->create([
            'recipient_email' => 'patient@example.com',
            'status' => Reminder::STATUS_PENDING,
            'remind_at' => now()->subMinute(),
            'sent_at' => null,
        ]);

        $this->artisan('reminders:send-due')
            ->assertExitCode(0);

        Mail::assertSent(ReminderMail::class, 1);
        Mail::assertSent(ReminderMail::class, fn (ReminderMail $mail) => $mail->reminder->is($reminder));

        $this->assertDatabaseHas('reminders', [
            'id' => $reminder->id,
            'status' => Reminder::STATUS_SENT,
            'error_message' => null,
        ]);
        $this->assertNotNull($reminder->fresh()->sent_at);
    }

    public function test_future_pending_reminder_is_not_sent(): void
    {
        Mail::fake();
        $reminder = Reminder::factory()->create([
            'recipient_email' => 'patient@example.com',
            'status' => Reminder::STATUS_PENDING,
            'remind_at' => now()->addHour(),
            'sent_at' => null,
        ]);

        $this->artisan('reminders:send-due')
            ->assertExitCode(0);

        Mail::assertNothingSent();
        $this->assertDatabaseHas('reminders', [
            'id' => $reminder->id,
            'status' => Reminder::STATUS_PENDING,
            'sent_at' => null,
        ]);
    }

    public function test_already_sent_reminder_is_not_sent_again(): void
    {
        Mail::fake();
        $reminder = Reminder::factory()->create([
            'recipient_email' => 'patient@example.com',
            'status' => Reminder::STATUS_SENT,
            'remind_at' => now()->subHour(),
            'sent_at' => now()->subMinutes(30),
        ]);

        $this->artisan('reminders:send-due')
            ->assertExitCode(0);

        Mail::assertNothingSent();
        $this->assertDatabaseHas('reminders', [
            'id' => $reminder->id,
            'status' => Reminder::STATUS_SENT,
        ]);
    }

    public function test_failed_reminder_is_not_sent_again(): void
    {
        Mail::fake();
        $reminder = Reminder::factory()->create([
            'recipient_email' => 'patient@example.com',
            'status' => Reminder::STATUS_FAILED,
            'remind_at' => now()->subHour(),
            'sent_at' => null,
            'error_message' => 'Previous failure.',
        ]);

        $this->artisan('reminders:send-due')
            ->assertExitCode(0);

        Mail::assertNothingSent();
        $this->assertDatabaseHas('reminders', [
            'id' => $reminder->id,
            'status' => Reminder::STATUS_FAILED,
            'error_message' => 'Previous failure.',
        ]);
    }

    public function test_reminder_with_blank_recipient_email_is_marked_sent_by_current_service_behavior(): void
    {
        Mail::fake();
        $reminder = Reminder::factory()->create([
            'recipient_email' => '',
            'status' => Reminder::STATUS_PENDING,
            'remind_at' => now()->subMinute(),
            'sent_at' => null,
        ]);

        $this->artisan('reminders:send-due')
            ->assertExitCode(0);

        Mail::assertSent(ReminderMail::class, 1);
        $this->assertDatabaseHas('reminders', [
            'id' => $reminder->id,
            'status' => Reminder::STATUS_SENT,
            'error_message' => null,
        ]);
    }

    public function test_send_due_command_respects_limit_option(): void
    {
        Mail::fake();
        Reminder::factory()->count(3)->create([
            'recipient_email' => 'patient@example.com',
            'status' => Reminder::STATUS_PENDING,
            'remind_at' => now()->subMinute(),
            'sent_at' => null,
        ]);

        $this->artisan('reminders:send-due', ['--limit' => 2])
            ->assertExitCode(0);

        Mail::assertSent(ReminderMail::class, 2);
        $this->assertSame(2, Reminder::query()->where('status', Reminder::STATUS_SENT)->count());
        $this->assertSame(1, Reminder::query()->where('status', Reminder::STATUS_PENDING)->count());
    }
}
