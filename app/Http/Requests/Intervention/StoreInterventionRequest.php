<?php

namespace App\Http\Requests\Intervention;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreInterventionRequest extends FormRequest
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
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'next_step' => ['nullable', 'string'],
            'intervention_date' => ['required', 'date'],
            'appointment_id' => ['nullable', 'integer', 'exists:appointments,id'],
            'performed_by_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'assigned_to_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'task_due_date' => ['nullable', 'date'],
            'total_cost' => ['nullable', 'numeric', 'min:0'],
            'paid_amount' => ['nullable', 'numeric', 'min:0'],
            'reminder_staff_at' => ['nullable', 'date'],
            'reminder_patient_at' => ['nullable', 'date'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $totalCost = (float) ($this->input('total_cost') ?? 0);
            $paidAmount = (float) ($this->input('paid_amount') ?? 0);

            if ($paidAmount > $totalCost) {
                $validator->errors()->add('paid_amount', 'Placeni iznos ne moze biti veci od ukupne cene intervencije.');
            }
        });
    }
}
