<?php

namespace App\Http\Requests\PatientPortal;

use Illuminate\Foundation\Http\FormRequest;

class SendPatientPortalInviteRequest extends FormRequest
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
            'email' => ['required', 'email'],
        ];
    }
}
