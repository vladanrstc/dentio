<?php

namespace App\Http\Requests\Api\V1\Patient;

use App\Models\Patient;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexPatientRequest extends FormRequest
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
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::in([
                Patient::STATUS_ACTIVE,
                Patient::STATUS_INACTIVE,
                Patient::STATUS_TRANSFERRED,
                Patient::STATUS_COMPLETED,
            ])],
            'primary_dentist_id' => ['nullable', 'integer'],
            'has_open_tasks' => ['nullable', 'boolean'],
            'has_debt' => ['nullable', 'boolean'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
