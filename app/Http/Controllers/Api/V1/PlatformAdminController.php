<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\PlatformAdmin\DashboardRequest;
use App\Http\Requests\Invite\SendOwnerInviteRequest;
use App\Http\Resources\Api\V1\CompanyResource;
use App\Http\Resources\Api\V1\InviteResource;
use App\Models\Company;
use App\Services\InviteService;
use App\Services\PlatformAdminService;
use Illuminate\Http\JsonResponse;

class PlatformAdminController extends ApiController
{
    public function __construct(
        private readonly InviteService $inviteService,
        private readonly PlatformAdminService $platformAdminService,
    ) {
    }

    public function dashboard(DashboardRequest $request): JsonResponse
    {
        return $this->respond([
            'summary' => $this->platformAdminService->summary(),
            'companies' => CompanyResource::collection(
                $this->platformAdminService->companies($request->searchTerm())
            ),
        ]);
    }

    public function company(Company $company): CompanyResource
    {
        $company = $this->platformAdminService->companyOverview((int) $company->id);
        abort_if($company === null, 404);

        return new CompanyResource($company);
    }

    public function inviteCompanyOwner(SendOwnerInviteRequest $request): InviteResource
    {
        return new InviteResource(
            $this->inviteService->sendOwnerInvite($request->validated('email'))
        );
    }
}
