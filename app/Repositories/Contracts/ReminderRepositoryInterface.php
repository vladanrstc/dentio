<?php

namespace App\Repositories\Contracts;

use App\Models\Reminder;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

interface ReminderRepositoryInterface
{
    /**
     * @param list<array<string, mixed>> $rows
     */
    public function createMany(array $rows): void;

    public function duePending(CarbonInterface $at, int $limit = 100): Collection;

    public function countDueForCompany(int $companyId): int;

    public function markSent(Reminder $reminder): Reminder;

    public function markFailed(Reminder $reminder, string $error): Reminder;
}

