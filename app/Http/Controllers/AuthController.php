<?php

namespace App\Http\Controllers;

use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function showLogin(): View|RedirectResponse
    {
        if (Auth::check()) {
            return $this->redirectByRole(Auth::user());
        }

        return view('auth.login');
    }

    public function login(LoginRequest $request): RedirectResponse
    {
        $credentials = $request->validated();

        if (! Auth::attempt($credentials, false)) {
            return back()
                ->withInput($request->except('password'))
                ->withErrors(['email' => 'Pogresan email ili lozinka.']);
        }

        $request->session()->regenerate();

        return $this->redirectByRole($request->user());
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login.show');
    }

    private function redirectByRole(User $user): RedirectResponse
    {
        if ($user->role === User::ROLE_PLATFORM_ADMIN) {
            return redirect()->route('admin.dashboard');
        }

        if ($user->role === User::ROLE_PATIENT) {
            return redirect()->away(rtrim((string) config('app.frontend_url'), '/').'/patient-portal');
        }

        return redirect()->route('dashboard.index');
    }
}
