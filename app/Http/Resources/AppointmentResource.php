<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Appointment
 */
class AppointmentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'patient_id' => $this->patient_id,
            'patient_name' => $this->whenLoaded('patient', fn () => $this->patient?->fullName()),
            'assigned_to' => $this->whenLoaded('assignedTo', fn () => $this->assignedTo?->fullName()),
            'scheduled_by' => $this->whenLoaded('scheduledBy', fn () => $this->scheduledBy?->fullName()),
            'starts_at' => $this->starts_at?->toIso8601String(),
            'ends_at' => $this->ends_at?->toIso8601String(),
            'type' => $this->type,
            'status' => $this->status,
            'notes' => $this->notes,
            'google_event_id' => $this->google_event_id,
        ];
    }
}

