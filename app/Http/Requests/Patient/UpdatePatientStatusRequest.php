<?php

namespace App\Http\Requests\Patient;

use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePatientStatusRequest extends FormRequest
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
            'manual_status' => [
                'required',
                Rule::in([
                    Patient::STATUS_ACTIVE,
                    Patient::STATUS_INACTIVE,
                    Patient::STATUS_TRANSFERRED,
                    Patient::STATUS_COMPLETED,
                ]),
            ],
            'manual_status_reason' => ['nullable', 'string', 'max:2000'],
        ];
    }
}

