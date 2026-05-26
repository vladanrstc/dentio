<?php

namespace App\Repositories\Eloquent;

use App\Models\Appointment;
use App\Models\Company;
use App\Models\User;
use App\Repositories\Contracts\CompanyRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class EloquentCompanyRepository implements CompanyRepositoryInterface
{
    public function create(array $data): Company
    {
        return Company::query()->create($data);
    }

    public function findById(int $id): ?Company
    {
        return Company::query()->find($id);
    }

    public function totalCount(): int
    {
        return Company::query()->count();
    }

    public function paginateWithOverview(?string $search, int $perPage = 15): LengthAwarePaginator
    {
        return Company::query()
            ->when($search, function (Builder $query, string $searchTerm): void {
                $query->where(function (Builder $inner) use ($searchTerm): void {
                    $inner->where('name', 'like', '%'.$searchTerm.'%')
                        ->orWhere('address', 'like', '%'.$searchTerm.'%')
                        ->orWhere('email', 'like', '%'.$searchTerm.'%');
                });
            })
            ->withCount([
                'users as staff_count' => fn (Builder $query) => $query->whereIn('role', [
                    User::ROLE_COMPANY_ADMIN,
                    User::ROLE_DENTIST,
                    User::ROLE_NURSE,
                ]),
                'users as dentists_count' => fn (Builder $query) => $query->where('role', User::ROLE_DENTIST),
                'users as nurses_count' => fn (Builder $query) => $query->where('role', User::ROLE_NURSE),
                'patients',
                'appointments as scheduled_appointments_count' => fn (Builder $query) => $query->where('status', Appointment::STATUS_SCHEDULED),
                'invites as pending_invites_count' => fn (Builder $query) => $query
                    ->whereNull('accepted_at')
                    ->whereNull('revoked_at')
                    ->where('expires_at', '>', Carbon::now()),
            ])
            ->orderBy('name')
            ->paginate($perPage);
    }

    public function findWithOverviewById(int $id): ?Company
    {
        return Company::query()
            ->whereKey($id)
            ->withCount([
                'users as staff_count' => fn (Builder $query) => $query->whereIn('role', [
                    User::ROLE_COMPANY_ADMIN,
                    User::ROLE_DENTIST,
                    User::ROLE_NURSE,
                ]),
                'patients',
                'appointments as scheduled_appointments_count' => fn (Builder $query) => $query->where('status', Appointment::STATUS_SCHEDULED),
                'interventions',
                'invites as pending_invites_count' => fn (Builder $query) => $query
                    ->whereNull('accepted_at')
                    ->whereNull('revoked_at')
                    ->where('expires_at', '>', Carbon::now()),
            ])
            ->with([
                'createdBy',
                'users' => fn ($query) => $query
                    ->whereIn('role', [User::ROLE_COMPANY_ADMIN, User::ROLE_DENTIST, User::ROLE_NURSE])
                    ->orderBy('role')
                    ->orderBy('first_name')
                    ->orderBy('last_name'),
                'invites' => fn ($query) => $query
                    ->latest()
                    ->limit(15),
                'patients' => fn ($query) => $query
                    ->latest()
                    ->limit(20),
            ])
            ->first();
    }

    public function updateCreatedBy(Company $company, int $userId): Company
    {
        $company->created_by_user_id = $userId;
        $company->save();

        return $company;
    }
}
