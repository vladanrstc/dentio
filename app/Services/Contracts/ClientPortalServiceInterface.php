<?php

namespace App\Services\Contracts;

use App\Models\Appointment;
use App\Models\Intervention;
use App\Models\Patient;
use App\Models\PatientTask;
use Illuminate\Support\Collection;

interface ClientPortalServiceInterface
{
    public function profile(Patient $patient): Patient;

    /**
     * @return Collection<int, Appointment>
     */
    public function appointments(Patient $patient): Collection;

    /**
     * @return Collection<int, Intervention>
     */
    public function interventions(Patient $patient): Collection;

    /**
     * @return Collection<int, PatientTask>
     */
    public function tasks(Patient $patient): Collection;
}
