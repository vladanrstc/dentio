<?php

namespace App\Repositories\Contracts;

use App\Models\Invite;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface InviteRepositoryInterface
{
    public function create(array $data): Invite;

    public function findByToken(string $token): ?Invite;

    public function findValidByToken(string $token): ?Invite;

    public function paginateForCompany(int $companyId, int $perPage = 20): LengthAwarePaginator;

    public function markAccepted(Invite $invite, int $userId): Invite;
}
