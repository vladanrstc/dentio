<?php

namespace App\Http\Requests\Appointment;

use App\Enums\AppointmentType;
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
        $companyId = (int) $this->user()->company_id;

        return [
            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'type' => ['required', Rule::enum(AppointmentType::class)],
            'assigned_user_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where(fn ($query) => $query->where('company_id', $companyId)),
            ],
            'notes' => ['nullable', 'string'],
            'reminder_staff_at' => ['nullable', 'date'],
            'reminder_patient_at' => ['nullable', 'date'],
        ];
    }
}
