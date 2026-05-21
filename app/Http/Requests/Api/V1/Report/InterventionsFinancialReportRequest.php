<?php

namespace App\Http\Requests\Api\V1\Report;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InterventionsFinancialReportRequest extends FormRequest
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
            'format' => ['nullable', Rule::in(['csv', 'xlsx', 'pdf'])],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'performed_by_user_id' => ['nullable', 'integer'],
            'has_outstanding' => ['nullable', 'boolean'],
        ];
    }
}
