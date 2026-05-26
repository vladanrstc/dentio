<?php

namespace App\Services\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;

interface AuthServiceInterface
{
    /**
     * @return array{token: string, user: array<string, mixed>}
     */
    public function login(string $email, string $password): array;

    /**
     * @return array<string, mixed>
     */
    public function userPayload(Authenticatable $user): array;
}
