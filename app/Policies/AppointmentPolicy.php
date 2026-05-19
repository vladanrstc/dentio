<?php

namespace App\Policies;

use App\Models\Appointment;
use App\Models\User;

class AppointmentPolicy
{
    public function view(User $user, Appointment $appointment): bool
    {
        return $this->sameCompany($user, $appointment);
    }

    public function update(User $user, Appointment $appointment): bool
    {
        return $this->sameCompany($user, $appointment);
    }

    public function use(User $user, Appointment $appointment): bool
    {
        return $this->sameCompany($user, $appointment);
    }

    private function sameCompany(User $user, Appointment $appointment): bool
    {
        return $user->company_id !== null
            && $user->company_id === $appointment->company_id
            && in_array($user->role, [User::ROLE_COMPANY_ADMIN, User::ROLE_DENTIST, User::ROLE_NURSE], true);
    }
}
