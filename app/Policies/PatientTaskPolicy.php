<?php

namespace App\Policies;

use App\Models\PatientTask;
use App\Models\User;

class PatientTaskPolicy
{
    public function update(User $user, PatientTask $task): bool
    {
        return $this->sameCompany($user, $task);
    }

    public function complete(User $user, PatientTask $task): bool
    {
        return $this->sameCompany($user, $task);
    }

    private function sameCompany(User $user, PatientTask $task): bool
    {
        return $user->company_id !== null
            && $user->company_id === $task->company_id
            && $task->patient?->company_id === $user->company_id
            && in_array($user->role, [User::ROLE_COMPANY_ADMIN, User::ROLE_DENTIST, User::ROLE_NURSE], true);
    }
}
