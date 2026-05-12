<?php

namespace App\Policies;

use App\Models\Intervention;
use App\Models\User;

class InterventionPolicy
{
    public function view(User $user, Intervention $intervention): bool
    {
        return $this->sameCompany($user, $intervention);
    }

    public function update(User $user, Intervention $intervention): bool
    {
        return $this->sameCompany($user, $intervention);
    }

    private function sameCompany(User $user, Intervention $intervention): bool
    {
        return $user->company_id !== null
            && $user->company_id === $intervention->company_id
            && in_array($user->role, [User::ROLE_COMPANY_ADMIN, User::ROLE_DENTIST, User::ROLE_NURSE], true);
    }
}
