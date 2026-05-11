<?php

namespace App\Repositories\Eloquent;

use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Support\Collection;

class EloquentUserRepository implements UserRepositoryInterface
{
    public function create(array $data): User
    {
        return User::query()->create($data);
    }

    public function totalCount(): int
    {
        return User::query()->count();
    }

    public function countByRole(string $role): int
    {
        return User::query()
            ->where('role', $role)
            ->count();
    }

    public function forCompany(int $companyId): Collection
    {
        return User::query()
            ->where('company_id', $companyId)
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get();
    }

    public function forCompanyByRoles(int $companyId, array $roles): Collection
    {
        return User::query()
            ->where('company_id', $companyId)
            ->whereIn('role', $roles)
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get();
    }
}
