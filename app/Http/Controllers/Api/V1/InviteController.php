<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\Invite\AcceptInviteRequest;
use App\Http\Resources\Api\V1\InviteResource;
use App\Models\Invite;
use App\Services\InviteService;
use Illuminate\Http\JsonResponse;

class InviteController extends ApiController
{
    public function __construct(
        private readonly InviteService $inviteService,
    ) {
    }

    public function show(string $token): InviteResource
    {
        $invite = $this->validInviteOrGone($token);

        return new InviteResource($invite->loadMissing('company'));
    }

    public function accept(AcceptInviteRequest $request, string $token): JsonResponse
    {
        $invite = $this->validInviteOrGone($token);

        $this->inviteService->acceptInvite($invite, $request->validated());

        return $this->message('Invite accepted successfully.', 201);
    }

    private function validInviteOrGone(string $token): Invite
    {
        $invite = $this->inviteService->findValidInviteByToken($token);
        abort_if($invite === null, 410, 'Pozivnica nije validna ili je istekla.');

        return $invite;
    }
}
