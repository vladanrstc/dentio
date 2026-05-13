<?php

namespace App\Repositories\Eloquent;

use App\Models\Appointment;
use App\Repositories\Contracts\AppointmentRepositoryInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class EloquentAppointmentRepository implements AppointmentRepositoryInterface
{
    public function create(array $data): Appointment
    {
        return Appointment::query()->create($data);
    }

    public function hasActiveOverlap(int $companyId, int $assignedUserId, mixed $startsAt, mixed $endsAt): bool
    {
        $startsAt = Carbon::parse($startsAt);
        $endsAt = Carbon::parse($endsAt);

        return Appointment::query()
            ->where('company_id', $companyId)
            ->where('assigned_user_id', $assignedUserId)
            ->where('status', Appointment::STATUS_SCHEDULED)
            ->where('starts_at', '<', $endsAt)
            ->where(function ($query) use ($startsAt): void {
                $query->where('ends_at', '>', $startsAt)
                    ->orWhere(function ($inner) use ($startsAt): void {
                        $inner->whereNull('ends_at')
                            ->where('starts_at', '>', $startsAt);
                    });
            })
            ->exists();
    }

    public function upcomingForCompany(int $companyId, int $limit = 10): Collection
    {
        return Appointment::query()
            ->where('company_id', $companyId)
            ->where('starts_at', '>=', Carbon::now()->subHours(1))
            ->where('status', Appointment::STATUS_SCHEDULED)
            ->with(['patient', 'assignedTo'])
            ->orderBy('starts_at')
            ->limit($limit)
            ->get();
    }

    public function countTodayForCompany(int $companyId): int
    {
        return Appointment::query()
            ->where('company_id', $companyId)
            ->whereBetween('starts_at', [Carbon::today(), Carbon::tomorrow()])
            ->where('status', Appointment::STATUS_SCHEDULED)
            ->count();
    }
}
