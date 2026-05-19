<?php

namespace App\Http\Requests\Patient;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePatientRequest extends FormRequest
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
            'first_name' => ['required', 'string', 'max:120'],
            'last_name' => ['required', 'string', 'max:120'],
            'address' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:80'],
            'email' => ['nullable', 'email', 'max:255'],
            'primary_dentist_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where('company_id', $this->companyId()),
            ],
            'notes' => ['nullable', 'string'],
        ];
    }

    private function companyId(): int
    {
        return (int) $this->user()?->company_id;
    }
}
