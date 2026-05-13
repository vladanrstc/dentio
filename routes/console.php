<?php

use App\Services\InviteService;
use App\Services\ReminderDispatchService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

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
