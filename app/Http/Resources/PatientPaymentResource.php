<?php

namespace App\Http\Resources;

use App\Models\PatientPayment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PatientPayment
 */
class PatientPaymentResource extends JsonResource
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
            'intervention_id' => $this->intervention_id,
            'type' => $this->type,
            'description' => $this->description,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'status' => $this->status,
            'payment_url' => $this->payment_url,
            'paid_at' => $this->paid_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
