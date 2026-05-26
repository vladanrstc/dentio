<?php

namespace App\Http\Resources;

use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Company
 */
class AdminCompanyDetailResource extends JsonResource
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
            'payments_enabled' => (bool) $this->payments_enabled,
            'subscription_status' => $this->subscription_status,
            'subscription_plan' => $this->subscription_plan,
            'subscription_current_period_end' => $this->subscription_current_period_end?->toIso8601String(),
            'current_period_end' => $this->subscription_current_period_end?->toIso8601String(),
            'subscription_cancel_at_period_end' => (bool) $this->subscription_cancel_at_period_end,
            'stripe_customer_id' => $this->stripe_customer_id,
            'stripe_subscription_id' => $this->stripe_subscription_id,
            'created_at' => $this->created_at?->toIso8601String(),
            'created_by' => $this->whenLoaded('createdBy', fn () => $this->createdBy ? [
                'id' => $this->createdBy->id,
                'name' => $this->createdBy->fullName(),
            ] : null),
            'kpi' => [
                'staff_count' => (int) ($this->staff_count ?? 0),
                'patients_count' => (int) ($this->patients_count ?? 0),
                'active_appointments_count' => (int) ($this->scheduled_appointments_count ?? 0),
                'interventions_count' => (int) ($this->interventions_count ?? 0),
                'pending_invites_count' => (int) ($this->pending_invites_count ?? 0),
            ],
            'staff' => $this->whenLoaded('users', fn () => $this->users->map(fn ($user) => [
                'id' => $user->id,
                'name' => $user->fullName(),
                'email' => $user->email,
                'phone' => $user->phone,
                'role' => $user->role,
                'created_at' => $user->created_at?->toIso8601String(),
            ])->values()),
            'latest_patients' => $this->whenLoaded('patients', fn () => $this->patients->map(fn ($patient) => [
                'id' => $patient->id,
                'full_name' => $patient->fullName(),
                'phone' => $patient->phone,
                'email' => $patient->email,
                'manual_status' => $patient->manual_status,
                'created_at' => $patient->created_at?->toIso8601String(),
            ])->values()),
            'latest_invites' => $this->whenLoaded('invites', fn () => AdminInviteResource::collection($this->invites)),
        ];
    }
}
