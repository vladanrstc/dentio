<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Intervention
 */
class InterventionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'patient_id' => $this->patient_id,
            'appointment_id' => $this->appointment_id,
            'performed_by_user_id' => $this->performed_by_user_id,
            'title' => $this->title,
            'description' => $this->description,
            'next_step' => $this->next_step,
            'intervention_date' => $this->intervention_date?->toDateString(),
            'total_cost' => (float) $this->total_cost,
            'paid_amount' => (float) $this->paid_amount,
            'outstanding_amount' => $this->outstandingAmount(),
            'patient' => new PatientResource($this->whenLoaded('patient')),
            'appointment' => new AppointmentResource($this->whenLoaded('appointment')),
            'performed_by' => new UserResource($this->whenLoaded('performedBy')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
