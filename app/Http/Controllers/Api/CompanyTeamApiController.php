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

    public function destroyInvite(Request $request, int $inviteId): JsonResponse
    {
        $invite = Invite::query()
            ->where('company_id', $request->user()->company_id)
            ->whereIn('role', [User::ROLE_DENTIST, User::ROLE_NURSE])
            ->whereKey($inviteId)
            ->first();

        abort_if($invite === null, Response::HTTP_NOT_FOUND);

        if ($invite->accepted_at !== null) {
            abort(Response::HTTP_FORBIDDEN, 'Prihvacene pozivnice ne mogu da se brisu.');
        }

        $invite->delete();

        return response()->json([
            'data' => [
                'deleted' => true,
            ],
        ]);
    }

    public function destroy(Request $request, int $userId): JsonResponse
    {
        $teamMember = User::query()
            ->where('company_id', $request->user()->company_id)
            ->whereKey($userId)
            ->first();

        abort_if($teamMember === null, Response::HTTP_NOT_FOUND);

        if (! in_array($teamMember->role, [User::ROLE_DENTIST, User::ROLE_NURSE], true)) {
            abort(Response::HTTP_FORBIDDEN, 'Nije dozvoljeno brisanje admin naloga.');
        }

        $email = mb_strtolower(trim((string) $teamMember->email));
        $companyId = $teamMember->company_id;

        $teamMember->delete();

        Invite::query()
            ->where('company_id', $companyId)
            ->whereRaw('LOWER(email) = ?', [$email])
            ->delete();

        return response()->json([
            'data' => [
                'deleted' => true,
                'id' => $userId,
            ],
        ]);
    }
}
