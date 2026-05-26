<?php

namespace App\Http\Requests\PatientPortal;

use Illuminate\Foundation\Http\FormRequest;

class AcceptPatientPortalInviteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'recaptcha_token' => ['nullable', 'string'],
        ];
    }
}
