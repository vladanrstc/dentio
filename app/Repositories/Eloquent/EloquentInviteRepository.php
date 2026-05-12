<?php

namespace App\Repositories\Eloquent;

use App\Models\Invite;
use App\Models\User;
use App\Repositories\Contracts\InviteRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;

class EloquentInviteRepository implements InviteRepositoryInterface
{
    public function create(array $data): Invite
    {
        return Invite::query()->create($data);
    }

    public function findByToken(string $token): ?Invite
    {
        return Invite::query()
            ->where('token', $token)
            ->first();
    }

    public function findValidByToken(string $token): ?Invite
    {
        return Invite::query()
            ->where('token', $token)
            ->whereNull('accepted_at')
            ->where('expires_at', '>', Carbon::now())
            ->first();
    }

    public function paginateForCompany(int $companyId, int $perPage = 20): LengthAwarePaginator
    {
        return Invite::query()
            ->where('company_id', $companyId)
            ->whereIn('role', [User::ROLE_DENTIST, User::ROLE_NURSE])
            ->latest()
            ->paginate($perPage);
    }

    public function markAccepted(Invite $invite, int $userId): Invite
    {
        $invite->accepted_at = Carbon::now();
        $invite->accepted_by_user_id = $userId;
        $invite->save();

        return $invite;
    }
}
