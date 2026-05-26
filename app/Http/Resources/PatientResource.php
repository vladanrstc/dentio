<?php

namespace App\Http\Resources;

use App\Models\Patient;
use App\Models\PatientTask;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Patient
 */
class PatientResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $openTaskCount = $this->open_tasks_count
            ?? $this->whenLoaded('tasks', fn () => $this->tasks->where('status', PatientTask::STATUS_OPEN)->count(), 0);

        $totalCost = $this->whenLoaded('interventions', fn () => (float) $this->interventions->sum('total_cost'), 0.0);
        $paidAmount = $this->whenLoaded('interventions', fn () => (float) $this->interventions->sum('paid_amount'), 0.0);

        return [
            'id' => $this->id,
            'full_name' => $this->fullName(),
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'address' => $this->address,
            'phone' => $this->phone,
            'email' => $this->email,
            'notes' => $this->notes,
            'primary_dentist_id' => $this->primary_dentist_id,
            'manual_status' => $this->manual_status,
            'manual_status_reason' => $this->manual_status_reason,
            'manual_status_changed_at' => $this->manual_status_changed_at?->toIso8601String(),
            'primary_dentist' => $this->whenLoaded('primaryDentist', fn () => $this->primaryDentist?->fullName()),
            'primary_dentist_user' => $this->whenLoaded('primaryDentist', fn () => $this->primaryDentist ? [
                'id' => $this->primaryDentist->id,
                'name' => $this->primaryDentist->fullName(),
            ] : null),
            'open_tasks_count' => $openTaskCount,
            'active_tasks' => $this->whenLoaded(
                'tasks',
                fn () => PatientTaskResource::collection($this->tasks->where('status', PatientTask::STATUS_OPEN)->values())
            ),
            'active_items' => $this->whenLoaded(
                'tasks',
                fn () => PatientTaskResource::collection($this->tasks->where('status', PatientTask::STATUS_OPEN)->values())
            ),
            'completed_tasks' => $this->whenLoaded(
                'tasks',
                fn () => PatientTaskResource::collection($this->tasks->where('status', PatientTask::STATUS_DONE)->values())
            ),
            'appointments' => $this->whenLoaded('appointments', fn () => AppointmentResource::collection($this->appointments)),
            'interventions' => $this->whenLoaded('interventions', fn () => InterventionResource::collection($this->interventions)),
            'financials' => [
                'total_cost' => $totalCost,
                'paid_amount' => $paidAmount,
                'outstanding_amount' => max(0, $totalCost - $paidAmount),
            ],
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
