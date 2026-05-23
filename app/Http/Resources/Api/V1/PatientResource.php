<?php

namespace App\Http\Resources\Api\V1;

use App\Models\PatientTask;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Patient
 */
class PatientResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $openTaskCount = $this->open_tasks_count
            ?? $this->whenLoaded('tasks', fn () => $this->tasks->where('status', PatientTask::STATUS_OPEN)->count(), 0);
        $totalCost = (float) ($this->total_cost_sum
            ?? $this->whenLoaded('interventions', fn () => $this->interventions->sum('total_cost'), 0));
        $paidAmount = (float) ($this->paid_amount_sum
            ?? $this->whenLoaded('interventions', fn () => $this->interventions->sum('paid_amount'), 0));

        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'user_id' => $this->user_id,
            'primary_dentist_id' => $this->primary_dentist_id,
            'manual_status_changed_by_user_id' => $this->manual_status_changed_by_user_id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'full_name' => $this->fullName(),
            'address' => $this->address,
            'phone' => $this->phone,
            'email' => $this->email,
            'manual_status' => $this->manual_status,
            'manual_status_reason' => $this->manual_status_reason,
            'manual_status_changed_at' => $this->manual_status_changed_at?->toIso8601String(),
            'notes' => $this->notes,
            'open_tasks_count' => $openTaskCount,
            'financials' => [
                'total_cost' => $totalCost,
                'paid_amount' => $paidAmount,
                'outstanding_amount' => max(0, $totalCost - $paidAmount),
            ],
            'primary_dentist' => new UserResource($this->whenLoaded('primaryDentist')),
            'manual_status_changed_by' => new UserResource($this->whenLoaded('manualStatusChangedBy')),
            'appointments' => AppointmentResource::collection($this->whenLoaded('appointments')),
            'interventions' => InterventionResource::collection($this->whenLoaded('interventions')),
            'tasks' => PatientTaskResource::collection($this->whenLoaded('tasks')),
            'status_logs' => PatientStatusLogResource::collection($this->whenLoaded('statusLogs')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
