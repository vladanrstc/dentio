<?php

namespace App\Services;

use App\Models\Company;
use App\Models\User;
use App\Repositories\Contracts\CompanyRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class PlatformAdminService
{
    public function __construct(
        private readonly CompanyRepositoryInterface $companyRepository,
        private readonly UserRepositoryInterface $userRepository,
    ) {
    }

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
}
