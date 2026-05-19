<?php

namespace Database\Factories;

use App\Models\Appointment;
use App\Models\Company;
use App\Models\Patient;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Appointment>
 */
class AppointmentFactory extends Factory
{
    public function definition(): array
    {
        $startsAt = fake()->dateTimeBetween('now', '+30 days');

        return [
            'company_id' => Company::factory(),
            'patient_id' => Patient::factory(),
            'scheduled_by_user_id' => null,
            'assigned_user_id' => null,
            'starts_at' => $startsAt,
            'ends_at' => (clone $startsAt)->modify('+30 minutes'),
            'type' => Appointment::TYPE_CHECKUP,
            'status' => Appointment::STATUS_SCHEDULED,
            'notes' => fake()->optional()->sentence(),
            'google_event_id' => null,
            'calendar_synced_at' => null,
            'reminder_staff_at' => null,
            'reminder_patient_at' => null,
        ];
    }
}
