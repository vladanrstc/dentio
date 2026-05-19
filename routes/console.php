<?php

use App\Mail\DailySuperadminReportMail;
use App\Services\DailySuperadminReportService;
use App\Services\InviteService;
use App\Services\ReminderDispatchService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('company:invite-owner {email}', function (string $email) {
    /** @var InviteService $service */
    $service = app(InviteService::class);
    $invite = $service->sendOwnerInvite($email);

    $this->info('Pozivnica je kreirana i dodata u red za slanje na: '.$invite->email);
    $this->line('Token: '.$invite->token);
    $this->line('Istice: '.$invite->expires_at->format('d.m.Y H:i'));
})->purpose('Send owner invite for a new dental company.');

Artisan::command('reminders:send-due {--limit=100}', function (int $limit) {
    /** @var ReminderDispatchService $service */
    $service = app(ReminderDispatchService::class);
    $queued = $service->sendDueReminders($limit);

    $this->info('Podsetnika dodatih u red za slanje: '.$queued);
})->purpose('Send due reminder emails.');

Artisan::command('reports:send-daily-superadmin', function () {
    /** @var DailySuperadminReportService $service */
    $service = app(DailySuperadminReportService::class);
    $report = $service->buildForPreviousDay();
    $superadminEmail = (string) config('mail.super_admin.address');

    Mail::to($superadminEmail)->queue(new DailySuperadminReportMail($report));

    $this->info('Dnevni izvestaj je dodat u red za slanje na: '.$superadminEmail);
    $this->line('Datum izvestaja: '.$report['date']);
})->purpose('Queue the previous day report email for the superadmin.');

Schedule::command('reports:send-daily-superadmin')->dailyAt('07:00');
