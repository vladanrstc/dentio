<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Invite;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Invite>
 */
class InviteFactory extends Factory
{
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'email' => fake()->unique()->safeEmail(),
            'role' => User::ROLE_DENTIST,
            'token' => Str::random(64),
            'invited_by_user_id' => null,
            'accepted_by_user_id' => null,
            'expires_at' => now()->addDays(7),
            'accepted_at' => null,
            'metadata' => [],
        ];
    }
}
