<?php

use App\Services\InviteService;
use App\Services\ReminderDispatchService;
use App\Services\Reports\ReportDispatchService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('company:invite-owner {email}', function (string $email) {
    /** @var InviteService $service */
    $service = app(InviteService::class);
    $invite = $service->sendOwnerInvite($email);

    $this->info('Pozivnica je kreirana i poslata na: '.$invite->email);
    $this->line('Token: '.$invite->token);
    $this->line('Istice: '.$invite->expires_at->format('d.m.Y H:i'));
})->purpose('Send owner invite for a new dental company.');

Artisan::command('reminders:send-due {--limit=100}', function (int $limit) {
    /** @var ReminderDispatchService $service */
    $service = app(ReminderDispatchService::class);
    $sent = $service->sendDueReminders($limit);

    $this->info('Poslato podsetnika: '.$sent);
})->purpose('Send due reminder emails.');

Artisan::command('reports:send-due', function () {
    /** @var ReportDispatchService $service */
    $service = app(ReportDispatchService::class);
    $sent = $service->sendDue();

    $this->info('Poslato izvestaja: '.$sent);
})->purpose('Send due scheduled report emails.');
