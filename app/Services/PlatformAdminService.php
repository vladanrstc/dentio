<?php

namespace App\Services;

use App\Exceptions\TenantResourceNotFoundException;
use App\Models\Appointment;
use App\Models\Company;
use App\Models\Invite;
use App\Models\Patient;
use App\Models\User;
use App\Repositories\Contracts\CompanyRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

class PlatformAdminService
{
    public function __construct(
        private readonly CompanyRepositoryInterface $companyRepository,
        private readonly UserRepositoryInterface $userRepository,
    ) {}

    /**
     * @return array<string, int>
     */
    public function summary(): array
    {
        return [
            'companies_total' => $this->companyRepository->totalCount(),
            'users_total' => $this->userRepository->totalCount(),
            'platform_admins_total' => $this->userRepository->countByRole(User::ROLE_PLATFORM_ADMIN),
            'company_admins_total' => $this->userRepository->countByRole(User::ROLE_COMPANY_ADMIN),
            'dentists_total' => $this->userRepository->countByRole(User::ROLE_DENTIST),
            'nurses_total' => $this->userRepository->countByRole(User::ROLE_NURSE),
        ];
    }

    public function companies(?string $search, int $perPage = 15): LengthAwarePaginator
    {
        return $this->companyRepository->paginateWithOverview($search, $perPage);
    }

    public function companyOverview(int $companyId): ?Company
    {
        return $this->companyRepository->findWithOverviewById($companyId);
    }

    public function deleteCompany(Company $company): void
    {
        Invite::query()
            ->where('company_id', $company->id)
            ->whereNull('accepted_at')
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);

        $company->delete();
    }

    public function assertCompanyExists(?Company $company): Company
    {
        if ($company === null) {
            throw new TenantResourceNotFoundException(__('errors.company_not_found'));
        }

        return $company;
    }

    /**
     * @return array<string, mixed>
     */
    public function apiDashboard(): array
    {
        return [
            'companies_total' => $this->companyRepository->totalCount(),
            'staff_total' => User::query()
                ->whereIn('role', [User::ROLE_COMPANY_ADMIN, User::ROLE_DENTIST, User::ROLE_NURSE])
                ->whereHas('company')
                ->count(),
            'patients_total' => Patient::query()
                ->whereHas('company')
                ->count(),
            'active_appointments_count' => Appointment::query()
                ->whereHas('company')
                ->where('status', Appointment::STATUS_SCHEDULED)
                ->count(),
            'pending_invites_count' => Invite::query()
                ->whereNull('accepted_at')
                ->whereNull('revoked_at')
                ->where('expires_at', '>', Carbon::now())
                ->where(function ($query): void {
                    $query->whereHas('company')
                        ->orWhere(function ($inner): void {
                            $inner->whereNull('company_id')
                                ->where('role', User::ROLE_COMPANY_ADMIN);
                        });
                })
                ->count(),
            'latest_companies' => $this->latestCompanies(),
            'latest_invites' => $this->latestInvites(),
        ];
    }

    /**
     * @return Collection<int, Company>
     */
    public function latestCompanies(int $limit = 5): Collection
    {
        return Company::query()
            ->withCount([
                'users as staff_count' => fn ($query) => $query->whereIn('role', [
                    User::ROLE_COMPANY_ADMIN,
                    User::ROLE_DENTIST,
                    User::ROLE_NURSE,
                ]),
                'patients',
                'appointments as scheduled_appointments_count' => fn ($query) => $query->where('status', Appointment::STATUS_SCHEDULED),
                'invites as pending_invites_count' => fn ($query) => $query
                    ->whereNull('accepted_at')
                    ->whereNull('revoked_at')
                    ->where('expires_at', '>', Carbon::now()),
            ])
            ->latest()
            ->limit($limit)
            ->get();
    }

    /**
     * @return Collection<int, Invite>
     */
    public function latestInvites(int $limit = 10): Collection
    {
        return Invite::query()
            ->with(['company', 'invitedBy', 'acceptedBy'])
            ->latest()
            ->limit($limit)
            ->get();
    }
}
