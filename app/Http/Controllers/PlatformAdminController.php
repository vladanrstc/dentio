<?php

namespace App\Http\Controllers;

use App\Http\Requests\Invite\SendOwnerInviteRequest;
use App\Services\InviteService;
use App\Services\PlatformAdminService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PlatformAdminController extends Controller
{
    public function __construct(
        private readonly InviteService $inviteService,
        private readonly PlatformAdminService $platformAdminService,
    ) {
    }

    public function dashboard(Request $request): View
    {
        $search = trim($request->string('search')->toString());

        return view('platform-admin.dashboard', [
            'summary' => $this->platformAdminService->summary(),
            'companies' => $this->platformAdminService->companies($search !== '' ? $search : null),
            'search' => $search,
        ]);
    }

    public function company(int $companyId): View
    {
        $company = $this->platformAdminService->companyOverview($companyId);
        abort_if($company === null, 404);

        return view('platform-admin.company', [
            'company' => $company,
        ]);
    }

    public function inviteOwner(SendOwnerInviteRequest $request): RedirectResponse
    {
        $this->inviteService->sendOwnerInvite($request->validated('email'));

        return redirect()
            ->route('admin.dashboard')
            ->with('status', 'Pozivnica za company owner je poslata.');
    }
}
