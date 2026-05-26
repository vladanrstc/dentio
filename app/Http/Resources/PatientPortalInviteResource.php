<?php

namespace App\Http\Resources;

use App\Models\PatientPortalInvite;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PatientPortalInvite
 */
class PatientPortalInviteResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $expired = $this->expires_at->isPast();
        $accepted = $this->accepted_at !== null;
        $revoked = $this->revoked_at !== null;

        return [
            'id' => $this->id,
            'email' => $this->email,
            'expires_at' => $this->expires_at?->toIso8601String(),
            'valid' => ! $expired && ! $accepted && ! $revoked,
            'expired' => $expired,
            'accepted' => $accepted,
            'revoked' => $revoked,
        ];
    }
}
