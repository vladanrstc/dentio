<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'company_id' => null,
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'phone' => fake()->phoneNumber(),
            'role' => User::ROLE_DENTIST,
        ];
    }

    public function platformAdmin(): static
    {
        return $this->state(fn (array $attributes) => [
            'company_id' => null,
            'role' => User::ROLE_PLATFORM_ADMIN,
        ]);
    }

    public function companyAdmin(): static
    {
        return $this->state(fn (array $attributes) => [
            'company_id' => Company::factory(),
            'role' => User::ROLE_COMPANY_ADMIN,
        ]);
    }

    public function dentist(): static
    {
        return $this->state(fn (array $attributes) => [
            'company_id' => Company::factory(),
            'role' => User::ROLE_DENTIST,
        ]);
    }

    public function nurse(): static
    {
        return $this->state(fn (array $attributes) => [
            'company_id' => Company::factory(),
            'role' => User::ROLE_NURSE,
        ]);
    }

    public function forCompany(Company $company): static
    {
        return $this->state(fn (array $attributes) => [
            'company_id' => $company->id,
        ]);
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
