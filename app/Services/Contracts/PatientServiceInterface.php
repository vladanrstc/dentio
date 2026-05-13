<?php

namespace App\Services\Contracts;

use App\Models\Patient;
use App\Models\PatientTask;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface PatientServiceInterface
{
    public function paginateForUser(User $user, ?string $search, int $perPage = 15): LengthAwarePaginator;

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(User $user, array $data): Patient;

    public function findForUser(User $user, int $patientId, bool $withRelations = false): ?Patient;

    public function assertAccessible(User $user, Patient $patient): Patient;

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(User $user, Patient $patient, array $data): Patient;

    public function delete(User $user, Patient $patient): void;

    public function changeManualStatus(User $user, Patient $patient, string $newStatus, ?string $reason): Patient;

    /**
     * @param  array<string, mixed>  $data
     */
    public function addTask(User $user, Patient $patient, array $data): PatientTask;

    public function completeTask(User $user, PatientTask $task): PatientTask;
}
