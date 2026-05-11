<?php

namespace App\Http\Controllers;

use App\Http\Requests\Invite\SendInviteRequest;
use App\Repositories\Contracts\InviteRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Services\InviteService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CompanyInviteController extends Controller
{
    public function __construct(
        private readonly InviteService $inviteService,
        private readonly InviteRepositoryInterface $inviteRepository,
        private readonly UserRepositoryInterface $userRepository,
    ) {
    }

    public function index(Request $request): View
    {
        $user = $request->user();
        $companyId = (int) $user->company_id;

        return view('invites.index', [
            'invites' => $this->inviteRepository->paginateForCompany($companyId),
            'staff' => $this->userRepository->forCompany($companyId),
        ]);
    }

    public function store(SendInviteRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $this->inviteService->sendStaffInvite(
            $request->user(),
            $data['email'],
            $data['role'],
        );

        return back()->with('status', 'Pozivnica je poslata.');
    }
}

