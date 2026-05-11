<?php

namespace App\Http\Requests\Appointment;

use App\Models\Appointment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAppointmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'type' => ['required', Rule::in([
                Appointment::TYPE_CHECKUP,
                Appointment::TYPE_INTERVENTION,
                Appointment::TYPE_CONTROL,
            ])],
            'assigned_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'notes' => ['nullable', 'string'],
            'reminder_staff_at' => ['nullable', 'date'],
            'reminder_patient_at' => ['nullable', 'date'],
        ];
    }
}

