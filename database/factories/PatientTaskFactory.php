<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Patient;
use App\Models\PatientTask;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PatientTask>
 */
class PatientTaskFactory extends Factory
{
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'patient_id' => Patient::factory(),
            'created_by_user_id' => null,
            'assigned_to_user_id' => null,
            'closed_by_user_id' => null,
            'description' => fake()->sentence(),
            'due_date' => fake()->optional()->date(),
            'status' => PatientTask::STATUS_OPEN,
            'closed_at' => null,
        ];
    }
}
