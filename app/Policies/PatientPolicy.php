<?php

namespace App\Policies;

use App\Models\Patient;
use App\Models\User;

class PatientPolicy
{
    public function view(User $user, Patient $patient): bool
    {
        return $this->sameCompany($user, $patient);
    }

    public function update(User $user, Patient $patient): bool
    {
        return $this->sameCompany($user, $patient);
    }

    public function changeStatus(User $user, Patient $patient): bool
    {
        return $this->sameCompany($user, $patient);
    }

    public function createAppointment(User $user, Patient $patient): bool
    {
        return $this->sameCompany($user, $patient);
    }

    public function createIntervention(User $user, Patient $patient): bool
    {
        return $this->sameCompany($user, $patient);
    }

    public function createTask(User $user, Patient $patient): bool
    {
        return $this->sameCompany($user, $patient);
    }

    private function sameCompany(User $user, Patient $patient): bool
    {
        return $user->company_id !== null
            && $user->company_id === $patient->company_id
            && in_array($user->role, [User::ROLE_COMPANY_ADMIN, User::ROLE_DENTIST, User::ROLE_NURSE], true);
    }
}
