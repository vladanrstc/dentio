<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthApiController extends Controller
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
    ) {}

    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = $request->validated();

        $user = $this->userRepository->findByEmail($credentials['email']);

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => [__('errors.invalid_credentials')],
            ]);
        }

        $token = $user->createToken('angular-api-token')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'company_id' => $user->company_id,
                'name' => $user->fullName(),
                'email' => $user->email,
                'role' => $user->role,
            ],
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'user' => [
                'id' => $user->id,
                'company_id' => $user->company_id,
                'name' => $user->fullName(),
                'email' => $user->email,
                'role' => $user->role,
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return response()->json([
            'message' => __('auth.logged_out'),
        ]);
    }
}
