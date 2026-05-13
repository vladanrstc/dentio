<?php

namespace App\Services\Contracts;

use App\Models\Intervention;
use App\Models\Patient;
use App\Models\User;

interface InterventionServiceInterface
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function record(User $actor, Patient $patient, array $data): Intervention;
}
