<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Invite
 */
class InviteResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'email' => $this->email,
            'role' => $this->role,
            'token' => $this->token,
            'invited_by_user_id' => $this->invited_by_user_id,
            'accepted_by_user_id' => $this->accepted_by_user_id,
            'expires_at' => $this->expires_at?->toIso8601String(),
            'accepted_at' => $this->accepted_at?->toIso8601String(),
            'metadata' => $this->metadata,
            'requires_company' => $this->company_id === null,
            'company' => new CompanyResource($this->whenLoaded('company')),
            'invited_by' => new UserResource($this->whenLoaded('invitedBy')),
            'accepted_by' => new UserResource($this->whenLoaded('acceptedBy')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
