<?php

namespace App\Services\Contracts;

use App\Models\User;

interface CompanyTeamServiceInterface
{
    public function deleteTeamMember(User $actor, User $teamMember): int;
}
