<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StaffApiController extends Controller
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $users = $this->userRepository->forCompanyByRoles((int) $request->user()->company_id, [
            User::ROLE_DENTIST,
            User::ROLE_COMPANY_ADMIN,
        ]);

        return response()->json([
            'data' => $users->map(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->fullName(),
                'email' => $user->email,
                'role' => $user->role,
            ])->values(),
        ]);
    }
}