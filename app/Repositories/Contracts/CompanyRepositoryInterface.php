<?php

namespace App\Repositories\Contracts;

use App\Models\Company;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface CompanyRepositoryInterface
{
    public function create(array $data): Company;

    public function findById(int $id): ?Company;

    public function totalCount(): int;

    public function paginateWithOverview(?string $search, int $perPage = 15): LengthAwarePaginator;

    public function findWithOverviewById(int $id): ?Company;

    public function updateCreatedBy(Company $company, int $userId): Company;
}
