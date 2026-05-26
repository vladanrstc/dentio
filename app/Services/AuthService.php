<?php

namespace App\Services;

use App\Models\Patient;
use App\Models\User;
use App\Repositories\Contracts\PatientRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Services\Contracts\AuthServiceInterface;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthService implements AuthServiceInterface
{
    public function __construct(
        private readonly UserRepositoryInterface $users,
        private readonly PatientRepositoryInterface $patients,
    ) {}

    public function login(string $email, string $password): array
    {
        $email = mb_strtolower(trim($email));
        $user = $this->users->findByEmail($email);

        if ($user && Hash::check($password, $user->password)) {
            return [
                'token' => $user->createToken('angular-api-token')->plainTextToken,
                'user' => $this->userPayload($user),
            ];
        }

        $patient = $this->matchingPatient($email, $password);

        if ($patient) {
            return [
                'token' => $patient->createToken('angular-api-token')->plainTextToken,
                'user' => $this->userPayload($patient),
            ];
        }

        throw ValidationException::withMessages([
            'email' => [__('errors.invalid_credentials')],
        ]);
    }

    public function userPayload(Authenticatable $user): array
    {
        if ($user instanceof Patient) {
            return [
                'id' => $user->id,
                'company_id' => $user->company_id,
                'name' => $user->fullName(),
                'email' => $user->email,
                'role' => Patient::ROLE_CLIENT,
            ];
        }

        /** @var User $user */
        return [
            'id' => $user->id,
            'company_id' => $user->company_id,
            'name' => $user->fullName(),
            'email' => $user->email,
            'role' => $user->role,
        ];
    }

    private function matchingPatient(string $email, string $password): ?Patient
    {
        $matches = $this->patients
            ->loginCandidatesByEmail($email)
            ->filter(fn (Patient $patient): bool => Hash::check($password, (string) $patient->password))
            ->values();

        if ($matches->count() !== 1) {
            return null;
        }

        /** @var Patient $patient */
        $patient = $matches->first();

        return $patient;
    }
}
