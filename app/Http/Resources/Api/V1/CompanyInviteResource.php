<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Invite
 */
class CompanyInviteResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'email' => $this->email,
            'role' => $this->role,
            'status' => $this->status(),
            'accepted_at' => $this->accepted_at?->toIso8601String(),
            'expires_at' => $this->expires_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }

    private function status(): string
    {
        if ($this->accepted_at !== null) {
            return 'accepted';
        }

        if ($this->expires_at?->isPast()) {
            return 'expired';
        }

        return 'pending';
    }
}
