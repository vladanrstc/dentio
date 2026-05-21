<?php

namespace App\Http\Requests\Invite;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SendInviteRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null
            && $user->role === User::ROLE_COMPANY_ADMIN
            && $user->company_id !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'email'],
            'role' => ['required', Rule::in([User::ROLE_DENTIST, User::ROLE_NURSE])],
            'company_id' => ['prohibited'],
            'company_name' => ['prohibited'],
            'company_address' => ['prohibited'],
            'company_phone' => ['prohibited'],
            'clinic_id' => ['prohibited'],
            'clinic_name' => ['prohibited'],
        ];
    }
}

