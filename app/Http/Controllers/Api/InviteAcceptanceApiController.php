<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Invite\AcceptInviteRequest;
use App\Http\Resources\InviteAcceptanceResource;
use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Services\InviteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class InviteAcceptanceApiController extends Controller
{
    public function __construct(
        private readonly InviteService $inviteService,
        private readonly UserRepositoryInterface $userRepository,
    ) {}

    public function show(string $token): InviteAcceptanceResource
    {
        $invite = $this->inviteService->findInviteByToken($token);
        abort_if($invite === null, Response::HTTP_NOT_FOUND, __('errors.invite_not_found'));

        return new InviteAcceptanceResource($invite);
    }

    public function store(AcceptInviteRequest $request, string $token): JsonResponse
    {
        $invite = $this->inviteService->findInviteByToken($token);
        abort_if($invite === null, Response::HTTP_NOT_FOUND, __('errors.invite_not_found'));

        if ($invite->accepted_at !== null) {
            abort(Response::HTTP_GONE, __('errors.invite_already_accepted'));
        }

        if ($invite->revoked_at !== null) {
            abort(Response::HTTP_GONE, __('errors.invite_revoked'));
        }

        if ($invite->expires_at === null || $invite->expires_at->isPast()) {
            return response()->json([
                'message' => __('errors.invite_expired'),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if ($this->userRepository->emailExists($invite->email)) {
            return response()->json([
                'message' => __('errors.email_already_exists'),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $data = $request->validated();
        $data['requires_company'] = $invite->company_id === null;

        $user = $this->inviteService->acceptInvite($invite, $data);
        $user->loadMissing('company');

        return response()->json([
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'company_id' => $user->company_id,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'full_name' => $user->fullName(),
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'role' => $user->role,
                ],
                'company' => $user->company ? [
                    'id' => $user->company->id,
                    'name' => $user->company->name,
                    'address' => $user->company->address,
                    'phone' => $user->company->phone,
                    'email' => $user->company->email,
                ] : null,
                'requires_company' => $invite->role === User::ROLE_COMPANY_ADMIN,
            ],
        ], Response::HTTP_CREATED);
    }
}
