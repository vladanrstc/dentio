<?php

namespace App\Services;

use App\Exceptions\InviteActionNotAllowedException;
use App\Exceptions\MissingCompanyContextException;
use App\Exceptions\TenantResourceNotFoundException;
use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Services\Contracts\CompanyTeamServiceInterface;

class CompanyTeamService implements CompanyTeamServiceInterface
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
    ) {}

    public function deleteTeamMember(User $actor, User $teamMember): int
    {
        if (! $actor->company_id) {
            throw new MissingCompanyContextException(__('errors.missing_company'));
        }

        $teamMember = $this->userRepository->findForCompany((int) $actor->company_id, $teamMember->id);

        if ($teamMember === null) {
            throw new TenantResourceNotFoundException(__('errors.user_not_found'));
        }

        if (! in_array($teamMember->role, [User::ROLE_DENTIST, User::ROLE_NURSE], true)) {
            throw new InviteActionNotAllowedException(__('errors.admin_delete_forbidden'));
        }

        $deletedId = $teamMember->id;
        $teamMember->delete();

        return $deletedId;
    }
}
