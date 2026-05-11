<?php

namespace App\Repositories\Contracts;

use App\Models\User;
use Illuminate\Support\Collection;

interface UserRepositoryInterface
{
    public function create(array $data): User;

    public function totalCount(): int;

    public function countByRole(string $role): int;

    public function forCompany(int $companyId): Collection;

    /**
     * @param list<string> $roles
     */
    public function forCompanyByRoles(int $companyId, array $roles): Collection;
}
