<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Company
 */
class CompanyResource extends JsonResource
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
            'email' => $this->email,
            'phone' => $this->phone,
            'created_by_user_id' => $this->created_by_user_id,
            'staff_count' => $this->when(isset($this->staff_count), $this->staff_count),
            'dentists_count' => $this->when(isset($this->dentists_count), $this->dentists_count),
            'nurses_count' => $this->when(isset($this->nurses_count), $this->nurses_count),
            'patients_count' => $this->when(isset($this->patients_count), $this->patients_count),
            'scheduled_appointments_count' => $this->when(
                isset($this->scheduled_appointments_count),
                $this->scheduled_appointments_count
            ),
            'interventions_count' => $this->when(isset($this->interventions_count), $this->interventions_count),
            'pending_invites_count' => $this->when(isset($this->pending_invites_count), $this->pending_invites_count),
            'created_by' => new UserResource($this->whenLoaded('createdBy')),
            'users' => UserResource::collection($this->whenLoaded('users')),
            'invites' => InviteResource::collection($this->whenLoaded('invites')),
            'patients' => PatientResource::collection($this->whenLoaded('patients')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
