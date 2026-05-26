<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Invite\SendInviteRequest;
use App\Http\Resources\CompanyInviteResource;
use App\Http\Resources\CompanyTeamMemberResource;
use App\Models\Invite;
use App\Models\User;
use App\Repositories\Contracts\InviteRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Services\Contracts\CompanyTeamServiceInterface;
use App\Services\InviteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class CompanyTeamApiController extends Controller
{
    public function __construct(
        private readonly InviteService $inviteService,
        private readonly InviteRepositoryInterface $inviteRepository,
        private readonly UserRepositoryInterface $userRepository,
        private readonly CompanyTeamServiceInterface $companyTeamService,
    ) {}

    public function team(Request $request): JsonResponse
    {
        $staff = $this->userRepository->forCompany((int) $request->user()->company_id);

        return response()->json([
            'data' => CompanyTeamMemberResource::collection($staff),
        ]);
    }

    public function invites(Request $request): JsonResponse
    {
        $companyId = (int) $request->user()->company_id;

        $invites = $this->inviteRepository->paginateForCompany($companyId);

        return CompanyInviteResource::collection($invites)->response();
    }

    public function storeInvite(SendInviteRequest $request): JsonResponse
    {
        $data = $request->validated();
        $invite = $this->inviteService->sendStaffInvite(
            $request->user(),
            $data['email'],
            $data['role'],
        );

        return (new CompanyInviteResource($invite))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function destroyInvite(Request $request, Invite $invite): JsonResponse
    {
        $this->inviteService->revokeTeamInviteForCompany((int) $request->user()->company_id, $invite->id);

        return response()->json([
            'data' => [
                'deleted' => true,
            ],
        ]);
    }

    public function resendInvite(Request $request, Invite $invite): JsonResponse
    {
        $invite = $this->inviteService->resendTeamInviteForCompany((int) $request->user()->company_id, $invite->id);

        return (new CompanyInviteResource($invite))->response();
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        $deletedId = $this->companyTeamService->deleteTeamMember($request->user(), $user);

        return response()->json([
            'data' => [
                'deleted' => true,
                'id' => $deletedId,
            ],
        ]);
    }
}
