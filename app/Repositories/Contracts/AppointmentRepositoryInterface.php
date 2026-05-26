<?php

namespace App\Repositories\Contracts;

use App\Models\Appointment;
use Illuminate\Support\Collection;

interface AppointmentRepositoryInterface
{
    public function create(array $data): Appointment;

    public function hasActiveOverlap(int $companyId, int $assignedUserId, mixed $startsAt, mixed $endsAt): bool;

    public function upcomingForCompany(int $companyId, int $limit = 10): Collection;

    public function countTodayForCompany(int $companyId): int;
}
