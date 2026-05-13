<?php

namespace App\Repositories\Contracts;

use App\Models\User;
use Illuminate\Support\Collection;

interface UserRepositoryInterface
{
    public function create(array $data): User;

    public function findByEmail(string $email): ?User;

    public function emailExists(string $email): bool;

    public function totalCount(): int;

    public function countByRole(string $role): int;

    public function forCompany(int $companyId): Collection;

    public function findForCompany(int $companyId, int $userId): ?User;

    /**
     * @param  list<string>  $roles
     */
    public function forCompanyByRoles(int $companyId, array $roles): Collection;
}
