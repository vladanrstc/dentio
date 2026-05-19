<?php

namespace App\Http\Requests\Appointment;

use App\Models\Appointment;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAppointmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null
            && $user->company_id !== null
            && in_array($user->role, [User::ROLE_COMPANY_ADMIN, User::ROLE_DENTIST, User::ROLE_NURSE], true);
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
            'assigned_user_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where('company_id', $this->companyId()),
            ],
            'notes' => ['nullable', 'string'],
            'reminder_staff_at' => ['nullable', 'date'],
            'reminder_patient_at' => ['nullable', 'date'],
        ];
    }

    private function companyId(): int
    {
        return (int) $this->user()?->company_id;
    }
}
