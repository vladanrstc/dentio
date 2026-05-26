<?php

namespace App\Http\Resources;

use App\Models\Intervention;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Intervention
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
            'patient_id' => $this->patient_id,
            'appointment_id' => $this->appointment_id,
            'title' => $this->title,
            'description' => $this->description,
            'next_step' => $this->next_step,
            'intervention_date' => $this->intervention_date?->toDateString(),
            'performed_by' => $this->whenLoaded('performedBy', fn () => $this->performedBy ? [
                'id' => $this->performedBy->id,
                'name' => $this->performedBy->fullName(),
            ] : null),
            'total_cost' => (float) $this->total_cost,
            'paid_amount' => (float) $this->paid_amount,
            'outstanding_amount' => $this->outstandingAmount(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
