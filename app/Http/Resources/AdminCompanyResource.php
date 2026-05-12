<?php

namespace App\Http\Resources;

use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Company
 */
class AdminCompanyResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'address' => $this->address,
            'phone' => $this->phone,
            'email' => $this->email,
            'staff_count' => (int) ($this->staff_count ?? 0),
            'patients_count' => (int) ($this->patients_count ?? 0),
            'active_appointments_count' => (int) ($this->scheduled_appointments_count ?? 0),
            'pending_invites_count' => (int) ($this->pending_invites_count ?? 0),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
