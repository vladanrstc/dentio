<?php

namespace App\Services\Contracts;

use App\Models\Patient;
use App\Models\PatientPortalInvite;
use App\Models\User;

interface PatientPortalInviteServiceInterface
{
    public function send(User $inviter, string $email): PatientPortalInvite;

    public function findByToken(string $token): ?PatientPortalInvite;

    public function accept(string $token, string $password): Patient;
}
