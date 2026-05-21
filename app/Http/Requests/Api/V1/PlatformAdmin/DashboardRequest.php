<?php

namespace App\Http\Requests\Api\V1\PlatformAdmin;

use Illuminate\Foundation\Http\FormRequest;

class DashboardRequest extends FormRequest
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
            'search' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function searchTerm(): ?string
    {
        $search = trim($this->string('search')->toString());

        return $search !== '' ? $search : null;
    }
}
