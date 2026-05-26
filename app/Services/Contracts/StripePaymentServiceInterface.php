<?php

namespace App\Services\Contracts;

use App\Models\Appointment;
use App\Models\PatientPayment;
use App\Models\User;
use Illuminate\Support\Collection;

interface StripePaymentServiceInterface
{
    /**
     * @return Collection<int, PatientPayment>
     */
    public function listForCompany(User $actor, ?int $patientId = null): Collection;

    /**
     * @param  array<string, mixed>  $data
     */
    public function createPatientPayment(User $actor, array $data): PatientPayment;

    /**
     * @param  array<string, mixed>  $data
     */
    public function createAppointmentPaymentSession(User $actor, Appointment $appointment, array $data): PatientPayment;

    /**
     * @param  array<string, mixed>  $event
     */
    public function handleWebhookEvent(array $event): void;
}
