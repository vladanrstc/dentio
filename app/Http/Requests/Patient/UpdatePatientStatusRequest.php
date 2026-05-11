<?php

namespace App\Http\Requests\Patient;

use App\Models\Patient;
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

