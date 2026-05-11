<?php

namespace App\Http\Requests\Invite;

use Illuminate\Foundation\Http\FormRequest;

class AcceptInviteRequest extends FormRequest
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
            'first_name' => ['required', 'string', 'max:120'],
            'last_name' => ['required', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:60'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'requires_company' => ['nullable', 'boolean'],
            'company_name' => ['required_if:requires_company,1', 'nullable', 'string', 'max:255'],
            'company_address' => ['required_if:requires_company,1', 'nullable', 'string', 'max:255'],
            'company_phone' => ['nullable', 'string', 'max:60'],
        ];
    }
}

