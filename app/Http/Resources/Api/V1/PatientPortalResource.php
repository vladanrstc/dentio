<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Appointment;
use App\Models\PatientTask;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Patient
 */
class PatientPortalResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $appointments = $this->whenLoaded('appointments', fn () => $this->appointments, collect());
        $upcomingAppointments = $appointments
            ->filter(fn (Appointment $appointment) => $appointment->starts_at !== null
                && $appointment->starts_at->isFuture()
                && $appointment->status === Appointment::STATUS_SCHEDULED)
            ->values();
        $appointmentHistory = $appointments
            ->reject(fn (Appointment $appointment) => $appointment->starts_at !== null
                && $appointment->starts_at->isFuture()
                && $appointment->status === Appointment::STATUS_SCHEDULED)
            ->values();
        $tasks = $this->whenLoaded('tasks', fn () => $this->tasks, collect());

        return [
            'patient' => [
                'id' => $this->id,
                'first_name' => $this->first_name,
                'last_name' => $this->last_name,
                'full_name' => $this->fullName(),
                'address' => $this->address,
                'phone' => $this->phone,
                'email' => $this->email,
                'manual_status' => $this->manual_status,
                'manual_status_reason' => $this->manual_status_reason,
                'primary_dentist' => $this->primaryDentist ? [
                    'id' => $this->primaryDentist->id,
                    'full_name' => $this->primaryDentist->fullName(),
                    'email' => $this->primaryDentist->email,
                    'phone' => $this->primaryDentist->phone,
                ] : null,
            ],
            'upcoming_appointments' => $upcomingAppointments->map(fn (Appointment $appointment) => $this->appointment($appointment)),
            'appointment_history' => $appointmentHistory->map(fn (Appointment $appointment) => $this->appointment($appointment)),
            'interventions' => $this->whenLoaded('interventions', fn () => $this->interventions
                ->map(fn ($intervention) => [
                    'id' => $intervention->id,
                    'appointment_id' => $intervention->appointment_id,
                    'title' => $intervention->title,
                    'description' => $intervention->description,
                    'next_step' => $intervention->next_step,
                    'intervention_date' => $intervention->intervention_date?->toDateString(),
                    'total_cost' => (float) $intervention->total_cost,
                    'paid_amount' => (float) $intervention->paid_amount,
                    'outstanding_amount' => $intervention->outstandingAmount(),
                    'performed_by' => $intervention->performedBy ? [
                        'id' => $intervention->performedBy->id,
                        'full_name' => $intervention->performedBy->fullName(),
                    ] : null,
                ])->values()),
            'open_tasks' => PatientTaskResource::collection(
                $tasks->where('status', PatientTask::STATUS_OPEN)->values(),
            ),
            'completed_tasks' => PatientTaskResource::collection(
                $tasks->where('status', PatientTask::STATUS_DONE)->values(),
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function appointment(Appointment $appointment): array
    {
        return [
            'id' => $appointment->id,
            'starts_at' => $appointment->starts_at?->toIso8601String(),
            'ends_at' => $appointment->ends_at?->toIso8601String(),
            'type' => $appointment->type,
            'status' => $appointment->status,
            'notes' => $appointment->notes,
            'assigned_to' => $appointment->assignedTo ? [
                'id' => $appointment->assignedTo->id,
                'full_name' => $appointment->assignedTo->fullName(),
            ] : null,
        ];
    }
}
