<?php

namespace App\Repositories\Contracts;

use App\Models\Intervention;
use App\Models\Patient;
use Illuminate\Support\Collection;

interface InterventionRepositoryInterface
{
    public function create(array $data): Intervention;

    public function forPatient(Patient $patient): Collection;

    public function outstandingTotalForCompany(int $companyId): float;
}

