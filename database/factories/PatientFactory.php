<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Patient;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Patient>
 */
class PatientFactory extends Factory
{
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'primary_dentist_id' => null,
            'manual_status_changed_by_user_id' => null,
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'address' => fake()->address(),
            'phone' => fake()->phoneNumber(),
            'email' => fake()->optional()->safeEmail(),
            'manual_status' => Patient::STATUS_ACTIVE,
            'manual_status_reason' => null,
            'manual_status_changed_at' => null,
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
