<?php

namespace App\Http\Resources;

use App\Models\Appointment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Appointment
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
            'assigned_to' => $this->whenLoaded('assignedTo', fn () => $this->assignedTo ? [
                'id' => $this->assignedTo->id,
                'name' => $this->assignedTo->fullName(),
            ] : null),
            'scheduled_by' => $this->whenLoaded('scheduledBy', fn () => $this->scheduledBy ? [
                'id' => $this->scheduledBy->id,
                'name' => $this->scheduledBy->fullName(),
            ] : null),
            'starts_at' => $this->starts_at?->toIso8601String(),
            'ends_at' => $this->ends_at?->toIso8601String(),
            'type' => $this->type,
            'status' => $this->status,
            'cancel_reason' => $this->cancel_reason,
            'notes' => $this->notes,
            'reminder_staff_at' => $this->reminder_staff_at?->toIso8601String(),
            'reminder_patient_at' => $this->reminder_patient_at?->toIso8601String(),
            'google_event_id' => $this->google_event_id,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
