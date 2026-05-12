<?php

namespace App\Http\Requests\Patient;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePatientTaskRequest extends FormRequest
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
            'description' => ['required', 'string', 'max:2000'],
            'due_date' => ['nullable', 'date'],
            'assigned_to_user_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where('company_id', $this->companyId()),
            ],
        ];
    }

    private function companyId(): int
    {
        return (int) $this->user()?->company_id;
    }
}
