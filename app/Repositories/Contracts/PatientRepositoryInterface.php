<?php

namespace App\Repositories\Contracts;

use App\Models\Patient;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface PatientRepositoryInterface
{
    public function paginateForCompany(int $companyId, ?string $search, int $perPage = 15): LengthAwarePaginator;

    public function createForCompany(int $companyId, array $data): Patient;

    public function findForCompany(int $companyId, int $patientId): ?Patient;

    public function findForCompanyByEmail(int $companyId, string $email): ?Patient;

    public function findWithRelationsForCompany(int $companyId, int $patientId): ?Patient;

    /**
     * @return Collection<int, Patient>
     */
    public function loginCandidatesByEmail(string $email): Collection;

    public function findClientProfile(int $patientId): ?Patient;

    public function update(Patient $patient, array $data): Patient;

    public function countForCompany(int $companyId): int;

    public function countWithOpenTasksForCompany(int $companyId): int;
}
