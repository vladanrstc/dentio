<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Invite\AcceptInviteRequest;
use App\Http\Resources\InviteAcceptanceResource;
use App\Models\User;
use App\Services\InviteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class InviteAcceptanceApiController extends Controller
{
    public function __construct(
        private readonly InviteService $inviteService,
    ) {}

    public function show(string $token): InviteAcceptanceResource
    {
        $invite = $this->inviteService->findInviteByToken($token);
        abort_if($invite === null, Response::HTTP_NOT_FOUND, 'Pozivnica ne postoji.');

        return new InviteAcceptanceResource($invite);
    }

    public function store(AcceptInviteRequest $request, string $token): JsonResponse
    {
        $invite = $this->inviteService->findInviteByToken($token);
        abort_if($invite === null, Response::HTTP_NOT_FOUND, 'Pozivnica ne postoji.');

        if ($invite->accepted_at !== null) {
            abort(Response::HTTP_GONE, 'Pozivnica je vec prihvacena.');
        }

        if ($invite->revoked_at !== null) {
            abort(Response::HTTP_GONE, 'Pozivnica je opozvana.');
        }

        if ($invite->expires_at === null || $invite->expires_at->isPast()) {
            return response()->json([
                'message' => 'Pozivnica je istekla.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if (User::query()->where('email', $invite->email)->exists()) {
            return response()->json([
                'message' => 'Korisnik sa ovom email adresom već postoji.',
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
