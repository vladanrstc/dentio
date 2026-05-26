<?php

namespace App\Http\Requests\Patient;

use App\Enums\PatientStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePatientStatusRequest extends FormRequest
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
            'manual_status' => [
                'required',
                Rule::enum(PatientStatus::class),
            ],
            'manual_status_reason' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
