<?php

namespace App\Http\Requests\Api\V1\Report;

use App\Models\Appointment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AppointmentsReportRequest extends FormRequest
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
            'assigned_user_id' => ['nullable', 'integer'],
            'type' => ['nullable', Rule::in([
                Appointment::TYPE_CHECKUP,
                Appointment::TYPE_INTERVENTION,
                Appointment::TYPE_CONTROL,
            ])],
            'status' => ['nullable', Rule::in([
                Appointment::STATUS_SCHEDULED,
                Appointment::STATUS_COMPLETED,
                Appointment::STATUS_CANCELLED,
            ])],
        ];
    }
}
