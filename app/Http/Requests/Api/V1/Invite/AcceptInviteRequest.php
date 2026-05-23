<?php

namespace App\Http\Requests\Api\V1\Invite;

use App\Models\Invite;
use App\Models\User;
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
        $isPatientInvite = $this->isPatientInvite();

        return [
            'first_name' => [$isPatientInvite ? 'nullable' : 'required', 'string', 'max:120'],
            'last_name' => [$isPatientInvite ? 'nullable' : 'required', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:60'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'company_name' => [$this->requiresCompany() ? 'required' : 'nullable', 'string', 'max:255'],
            'company_address' => [$this->requiresCompany() ? 'required' : 'nullable', 'string', 'max:255'],
            'company_phone' => ['nullable', 'string', 'max:60'],
        ];
    }

    private function requiresCompany(): bool
    {
        $token = (string) $this->route('token');

        return Invite::query()
            ->where('token', $token)
            ->whereNull('company_id')
            ->exists();
    }

    private function isPatientInvite(): bool
    {
        $token = (string) $this->route('token');

        return Invite::query()
            ->where('token', $token)
            ->where('role', User::ROLE_PATIENT)
            ->exists();
    }
}
