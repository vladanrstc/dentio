<?php

namespace App\Http\Resources;

use App\Models\Invite;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Invite
 */
class AdminInviteResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'company_name' => $this->whenLoaded('company', fn () => $this->company?->name),
            'email' => $this->email,
            'role' => $this->role,
            'status' => $this->accepted_at !== null ? 'accepted' : ($this->expires_at?->isPast() ? 'expired' : 'pending'),
            'invited_by' => $this->whenLoaded('invitedBy', fn () => $this->invitedBy ? [
                'id' => $this->invitedBy->id,
                'name' => $this->invitedBy->fullName(),
            ] : null),
            'accepted_by' => $this->whenLoaded('acceptedBy', fn () => $this->acceptedBy ? [
                'id' => $this->acceptedBy->id,
                'name' => $this->acceptedBy->fullName(),
            ] : null),
            'expires_at' => $this->expires_at?->toIso8601String(),
            'accepted_at' => $this->accepted_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
