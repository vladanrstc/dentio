<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Invite\SendOwnerInviteRequest;
use App\Http\Resources\AdminCompanyDetailResource;
use App\Http\Resources\AdminCompanyResource;
use App\Http\Resources\AdminInviteResource;
use App\Models\Company;
use App\Models\Invite;
use App\Services\InviteService;
use App\Services\PlatformAdminService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PlatformAdminApiController extends Controller
{
    public function __construct(
        private readonly InviteService $inviteService,
        private readonly PlatformAdminService $platformAdminService,
    ) {}

    public function dashboard(): JsonResponse
    {
        $dashboard = $this->platformAdminService->apiDashboard();

        return response()->json([
            'data' => [
                'companies_total' => $dashboard['companies_total'],
                'staff_total' => $dashboard['staff_total'],
                'patients_total' => $dashboard['patients_total'],
                'active_appointments_count' => $dashboard['active_appointments_count'],
                'pending_invites_count' => $dashboard['pending_invites_count'],
                'latest_companies' => AdminCompanyResource::collection($dashboard['latest_companies']),
                'latest_invites' => AdminInviteResource::collection($dashboard['latest_invites']),
            ],
        ]);
    }

    public function companies(Request $request): JsonResponse
    {
        $search = trim($request->string('search')->toString());
        $companies = $this->platformAdminService->companies($search !== '' ? $search : null);

        return AdminCompanyResource::collection($companies)->response();
    }

    public function company(int $companyId): AdminCompanyDetailResource
    {
        $company = $this->platformAdminService->companyOverview($companyId);
        abort_if($company === null, Response::HTTP_NOT_FOUND);

        return new AdminCompanyDetailResource($company);
    }

    public function inviteOwner(SendOwnerInviteRequest $request): JsonResponse
    {
        $invite = $this->inviteService->sendOwnerInvite($request->validated('email'));

        return (new AdminInviteResource($invite))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function destroyCompany(int $companyId): JsonResponse
    {
        $company = Company::query()->find($companyId);
        abort_if($company === null, Response::HTTP_NOT_FOUND);

        Invite::query()
            ->where('company_id', $company->id)
            ->delete();

        $company->delete();

        return response()->json([
            'data' => [
                'deleted' => true,
            ],
        ]);
    }

    public function destroyInvite(int $inviteId): JsonResponse
    {
        $invite = Invite::query()->find($inviteId);
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
}
