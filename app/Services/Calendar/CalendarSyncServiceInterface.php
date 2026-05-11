<?php

namespace App\Services\Calendar;

use App\Models\Appointment;

interface CalendarSyncServiceInterface
{
    public function syncAppointment(Appointment $appointment): ?string;
}

