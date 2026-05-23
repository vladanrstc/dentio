<?php

namespace App\Http\Controllers;

use App\Http\Requests\Invite\AcceptInviteRequest;
use App\Services\InviteService;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class InviteAcceptanceController extends Controller
{
    public function __construct(
        private readonly InviteService $inviteService,
    ) {
    }

    public function show(string $token): View
    {
        $invite = $this->inviteService->findValidInviteByToken($token);
        abort_if($invite === null, 410, 'Pozivnica nije validna ili je istekla.');

        return view('invites.accept', [
            'invite' => $invite,
            'requiresCompany' => $invite->company_id === null,
            'isPatientInvite' => $invite->role === User::ROLE_PATIENT,
        ]);
    }

    public function store(AcceptInviteRequest $request, string $token): RedirectResponse
    {
        $invite = $this->inviteService->findValidInviteByToken($token);
        if ($invite === null) {
            return back()->withErrors(['invite' => 'Pozivnica nije validna ili je istekla.']);
        }

        $user = $this->inviteService->acceptInvite($invite, $request->validated());
        Auth::login($user);
        $request->session()->regenerate();

        if ($user->role === User::ROLE_PATIENT) {
            return redirect()->away(rtrim((string) config('app.frontend_url'), '/').'/patient-portal');
        }

        return redirect()->route('dashboard.index')
            ->with('status', 'Naloga je aktiviran i spreman za rad.');
    }
}

