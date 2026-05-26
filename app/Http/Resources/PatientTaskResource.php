<?php

namespace App\Http\Resources;

use App\Models\PatientTask;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PatientTask
 */
class PatientTaskResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'patient_id' => $this->patient_id,
            'description' => $this->description,
            'due_date' => $this->due_date?->toDateString(),
            'status' => $this->status,
            'assigned_to' => $this->whenLoaded('assignedTo', fn () => $this->assignedTo ? [
                'id' => $this->assignedTo->id,
                'name' => $this->assignedTo->fullName(),
            ] : null),
            'created_by' => $this->whenLoaded('createdBy', fn () => $this->createdBy ? [
                'id' => $this->createdBy->id,
                'name' => $this->createdBy->fullName(),
            ] : null),
            'closed_by' => $this->whenLoaded('closedBy', fn () => $this->closedBy ? [
                'id' => $this->closedBy->id,
                'name' => $this->closedBy->fullName(),
            ] : null),
            'closed_at' => $this->closed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
