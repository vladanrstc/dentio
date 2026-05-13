<?php

namespace App\Services\Contracts;

use App\Models\Appointment;
use App\Models\Patient;
use App\Models\User;

interface AppointmentServiceInterface
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function schedule(User $actor, Patient $patient, array $data): Appointment;

    public function cancel(User $actor, Appointment $appointment, ?string $reason = null): Appointment;
}
