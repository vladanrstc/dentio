<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Reminder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Reminder>
 */
class ReminderFactory extends Factory
{
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'patient_id' => null,
            'appointment_id' => null,
            'intervention_id' => null,
            'recipient_email' => fake()->safeEmail(),
            'recipient_type' => Reminder::TYPE_PATIENT,
            'remind_at' => now()->addDay(),
            'subject' => fake()->sentence(),
            'body' => fake()->optional()->paragraph(),
            'status' => Reminder::STATUS_PENDING,
            'sent_at' => null,
            'error_message' => null,
        ];
    }
}
