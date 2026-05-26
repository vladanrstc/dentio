<?php

namespace App\Http\Requests\Report;

use App\Enums\ReportFormat;
use App\Enums\ReportFrequency;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateReportSubscriptionRequest extends FormRequest
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
            'frequency' => ['required', Rule::enum(ReportFrequency::class)],
            'format' => ['required', Rule::enum(ReportFormat::class)],
            'filters' => ['nullable', 'array'],
        ];
    }
}
