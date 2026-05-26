<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\Contracts\AuthServiceInterface;
use App\Services\RecaptchaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

class AuthApiController extends Controller
{
    public function __construct(
        private readonly AuthServiceInterface $authService,
        private readonly RecaptchaService $recaptcha,
    ) {}

    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = $request->validated();
        $this->recaptcha->verify($credentials['recaptcha_token'] ?? null);

        return response()->json($this->authService->login(
            $credentials['email'],
            $credentials['password'],
        ));
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'user' => $this->authService->userPayload($user),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $token = $request->user()?->currentAccessToken();
        $token?->delete();

        if ($request->bearerToken() !== null) {
            PersonalAccessToken::findToken($request->bearerToken())?->delete();
        }

        return response()->json([
            'message' => __('auth.logged_out'),
        ]);
    }
}
