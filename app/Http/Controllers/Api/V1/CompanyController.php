<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Invite\SendInviteRequest;
use App\Http\Resources\Api\V1\CompanyInviteResource;
use App\Http\Resources\Api\V1\DashboardResource;
use App\Http\Resources\Api\V1\InviteResource;
use App\Http\Resources\Api\V1\UserResource;
use App\Models\User;
use App\Repositories\Contracts\InviteRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Services\DashboardService;
use App\Services\InviteService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CompanyController extends ApiController
{
    public function __construct(
        private readonly DashboardService $dashboardService,
        private readonly InviteService $inviteService,
        private readonly InviteRepositoryInterface $inviteRepository,
        private readonly UserRepositoryInterface $userRepository,
    ) {
    }

    public function dashboard(Request $request): DashboardResource
    {
        return new DashboardResource($this->dashboardService->summaryForUser($request->user()));
    }

    public function staff(Request $request): AnonymousResourceCollection
    {
        return UserResource::collection(
            $this->userRepository->forCompany((int) $request->user()->company_id)
        );
    }

    public function team(Request $request): AnonymousResourceCollection
    {
        return UserResource::collection(
            $this->userRepository->forCompanyByRoles((int) $request->user()->company_id, [
                User::ROLE_COMPANY_ADMIN,
                User::ROLE_DENTIST,
                User::ROLE_NURSE,
            ])
        );
    }

    public function invites(Request $request): AnonymousResourceCollection
    {
        return CompanyInviteResource::collection(
            $this->inviteRepository->paginateForCompany((int) $request->user()->company_id)
        );
    }

    public function invite(SendInviteRequest $request): InviteResource
    {
        $data = $request->validated();

        return new InviteResource(
            $this->inviteService->sendStaffInvite($request->user(), $data['email'], $data['role'])
        );
    }
}
