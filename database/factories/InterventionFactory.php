<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Intervention;
use App\Models\Patient;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Intervention>
 */
class InterventionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'patient_id' => Patient::factory(),
            'appointment_id' => null,
            'performed_by_user_id' => null,
            'title' => fake()->words(3, true),
            'description' => fake()->optional()->sentence(),
            'next_step' => fake()->optional()->sentence(),
            'intervention_date' => fake()->date(),
            'total_cost' => fake()->randomFloat(2, 0, 50000),
            'paid_amount' => fake()->randomFloat(2, 0, 25000),
        ];
    }
}
