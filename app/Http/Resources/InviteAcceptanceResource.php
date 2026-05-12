<?php

namespace App\Http\Resources;

use App\Models\Invite;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Invite
 */
class InviteAcceptanceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $accepted = $this->accepted_at !== null;
        $expired = $this->expires_at?->isPast() ?? true;
        $revoked = $this->revoked_at !== null;

        return [
            'email' => $this->email,
            'role' => $this->role,
            'expires_at' => $this->expires_at?->toIso8601String(),
            'valid' => ! $accepted && ! $expired && ! $revoked,
            'accepted' => $accepted,
            'expired' => $expired,
            'revoked' => $revoked,
            'requires_company' => $this->company_id === null,
        ];
    }
}
