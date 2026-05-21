<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Appointment
 */
class AppointmentResource extends JsonResource
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
            'scheduled_by_user_id' => $this->scheduled_by_user_id,
            'assigned_user_id' => $this->assigned_user_id,
            'starts_at' => $this->starts_at?->toIso8601String(),
            'ends_at' => $this->ends_at?->toIso8601String(),
            'type' => $this->type,
            'status' => $this->status,
            'notes' => $this->notes,
            'google_event_id' => $this->google_event_id,
            'calendar_synced_at' => $this->calendar_synced_at?->toIso8601String(),
            'reminder_staff_at' => $this->reminder_staff_at?->toIso8601String(),
            'reminder_patient_at' => $this->reminder_patient_at?->toIso8601String(),
            'patient' => new PatientResource($this->whenLoaded('patient')),
            'scheduled_by' => new UserResource($this->whenLoaded('scheduledBy')),
            'assigned_to' => new UserResource($this->whenLoaded('assignedTo')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
