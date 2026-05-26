<?php

namespace App\Services;

use App\Exceptions\MissingCompanyContextException;
use App\Exceptions\PaymentActionNotAllowedException;
use App\Exceptions\TenantResourceNotFoundException;
use App\Mail\PatientPaymentLinkMail;
use App\Models\Appointment;
use App\Models\Intervention;
use App\Models\Patient;
use App\Models\PatientPayment;
use App\Models\User;
use App\Services\Contracts\StripeGatewayInterface;
use App\Services\Contracts\StripePaymentServiceInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class StripePaymentService implements StripePaymentServiceInterface
{
    public function __construct(
        private readonly StripeGatewayInterface $stripe,
    ) {}

    public function listForCompany(User $actor, ?int $patientId = null): Collection
    {
        $companyId = $this->companyIdOrFail($actor);

        return PatientPayment::query()
            ->where('company_id', $companyId)
            ->when($patientId !== null, fn ($query) => $query->where('patient_id', $patientId))
            ->latest()
            ->get();
    }

    public function createPatientPayment(User $actor, array $data): PatientPayment
    {
        $companyId = $this->companyIdOrFail($actor);
        $this->ensurePaymentsEnabled($actor);

        $patient = Patient::query()
            ->where('company_id', $companyId)
            ->whereKey((int) $data['patient_id'])
            ->first();

        if (! $patient) {
            throw ValidationException::withMessages([
                'patient_id' => [__('errors.payment_patient_not_found')],
            ]);
        }

        $appointment = $this->appointmentForPatient($companyId, $patient, $data['appointment_id'] ?? null);
        $intervention = $this->interventionForPatient($companyId, $patient, $data['intervention_id'] ?? null);

        return $this->createCheckoutPayment($actor, $patient, [
            'appointment_id' => $appointment?->id,
            'intervention_id' => $intervention?->id,
            'type' => $this->paymentType($appointment, $intervention),
            'amount' => (int) $data['amount'],
            'currency' => mb_strtolower($data['currency'] ?? (string) config('services.stripe.currency', 'usd')),
            'description' => $data['description'] ?? $this->defaultDescription($appointment, $intervention),
        ]);
    }

    public function createAppointmentPaymentSession(User $actor, Appointment $appointment, array $data): PatientPayment
    {
        $companyId = $this->companyIdOrFail($actor);
        $this->ensurePaymentsEnabled($actor);

        if ((int) $appointment->company_id !== $companyId) {
            throw new TenantResourceNotFoundException(__('errors.appointment_not_found'));
        }

        $appointment->loadMissing('patient');

        return $this->createCheckoutPayment($actor, $appointment->patient, [
            'appointment_id' => $appointment->id,
            'intervention_id' => null,
            'type' => PatientPayment::TYPE_APPOINTMENT,
            'amount' => (int) $data['amount'],
            'currency' => mb_strtolower($data['currency'] ?? (string) config('services.stripe.currency', 'usd')),
            'description' => $data['description'] ?? 'Placanje termina',
        ]);
    }

    public function handleWebhookEvent(array $event): void
    {
        $type = $event['type'] ?? null;
        $object = $event['data']['object'] ?? [];

        match ($type) {
            'checkout.session.completed' => $this->handleCheckoutCompleted($object),
            'checkout.session.expired' => $this->handleCheckoutExpired($object),
            'payment_intent.payment_failed' => $this->handlePaymentIntentFailed($object),
            default => null,
        };
    }

    private function createCheckoutPayment(User $actor, Patient $patient, array $data): PatientPayment
    {
        $payment = PatientPayment::query()->create([
            'company_id' => $patient->company_id,
            'patient_id' => $patient->id,
            'appointment_id' => $data['appointment_id'],
            'intervention_id' => $data['intervention_id'],
            'created_by_user_id' => $actor->id,
            'type' => $data['type'],
            'description' => $data['description'],
            'amount' => $data['amount'],
            'currency' => $data['currency'],
            'status' => PatientPayment::STATUS_PENDING,
            'metadata' => [],
        ]);

        $session = $this->stripe->createCheckoutSession($this->checkoutSessionPayload($payment, $patient));

        $payment->forceFill([
            'stripe_checkout_session_id' => $session['id'] ?? null,
            'stripe_payment_intent_id' => $session['payment_intent'] ?? null,
            'stripe_customer_id' => $session['customer'] ?? null,
            'payment_url' => $session['url'] ?? null,
        ])->save();

        if (is_string($patient->email) && $patient->email !== '' && is_string($payment->payment_url)) {
            Mail::to($patient->email)->send(new PatientPaymentLinkMail($payment));
        }

        return $payment->refresh()->loadMissing(['patient', 'appointment', 'intervention']);
    }

    /**
     * @return array<string, mixed>
     */
    private function checkoutSessionPayload(PatientPayment $payment, Patient $patient): array
    {
        $frontendUrl = rtrim((string) config('app.frontend_url'), '/');

        return [
            'mode' => 'payment',
            'customer_email' => $patient->email,
            'line_items' => [[
                'price_data' => [
                    'currency' => $payment->currency,
                    'product_data' => [
                        'name' => $payment->description ?: 'Dentio placanje',
                    ],
                    'unit_amount' => $payment->amount,
                ],
                'quantity' => 1,
            ]],
            'metadata' => [
                'payment_id' => (string) $payment->id,
                'company_id' => (string) $payment->company_id,
                'patient_id' => (string) $payment->patient_id,
                'appointment_id' => (string) ($payment->appointment_id ?? ''),
                'intervention_id' => (string) ($payment->intervention_id ?? ''),
            ],
            'success_url' => $frontendUrl.'/payments/success?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => $frontendUrl.'/payments/cancel',
        ];
    }

    private function handleCheckoutCompleted(array $session): void
    {
        if (($session['mode'] ?? null) !== 'payment') {
            return;
        }

        $payment = $this->paymentFromSession($session);
        if (! $payment) {
            return;
        }

        DB::transaction(function () use ($payment, $session): void {
            $lockedPayment = PatientPayment::query()
                ->whereKey($payment->id)
                ->lockForUpdate()
                ->first();

            if (! $lockedPayment || $lockedPayment->isPaid()) {
                return;
            }

            $lockedPayment->forceFill([
                'status' => PatientPayment::STATUS_PAID,
                'stripe_payment_intent_id' => $session['payment_intent'] ?? $lockedPayment->stripe_payment_intent_id,
                'stripe_customer_id' => $session['customer'] ?? $lockedPayment->stripe_customer_id,
                'paid_at' => Carbon::now(),
            ])->save();

            $this->applyPaymentToFinancials($lockedPayment);
        });
    }

    private function handleCheckoutExpired(array $session): void
    {
        $payment = $this->paymentFromSession($session);

        if ($payment && ! $payment->isPaid()) {
            $payment->forceFill(['status' => PatientPayment::STATUS_EXPIRED])->save();
        }
    }

    private function handlePaymentIntentFailed(array $intent): void
    {
        $paymentIntentId = $intent['id'] ?? null;
        if (! is_string($paymentIntentId) || $paymentIntentId === '') {
            return;
        }

        PatientPayment::query()
            ->where('stripe_payment_intent_id', $paymentIntentId)
            ->where('status', '!=', PatientPayment::STATUS_PAID)
            ->update(['status' => PatientPayment::STATUS_FAILED]);
    }

    private function paymentFromSession(array $session): ?PatientPayment
    {
        $paymentId = $session['metadata']['payment_id'] ?? null;
        if ($paymentId) {
            return PatientPayment::query()->find((int) $paymentId);
        }

        $sessionId = $session['id'] ?? null;
        if (! is_string($sessionId) || $sessionId === '') {
            return null;
        }

        return PatientPayment::query()
            ->where('stripe_checkout_session_id', $sessionId)
            ->first();
    }

    private function applyPaymentToFinancials(PatientPayment $payment): void
    {
        if (! $payment->intervention_id) {
            return;
        }

        $intervention = Intervention::query()->find($payment->intervention_id);
        if (! $intervention) {
            return;
        }

        $amount = round($payment->amount / 100, 2);
        $intervention->paid_amount = min(
            (float) $intervention->total_cost,
            (float) $intervention->paid_amount + $amount
        );
        $intervention->save();
    }

    private function ensurePaymentsEnabled(User $actor): void
    {
        $actor->loadMissing('company');

        if (! $actor->company?->payments_enabled) {
            throw new PaymentActionNotAllowedException(__('errors.payments_disabled'));
        }
    }

    private function appointmentForPatient(int $companyId, Patient $patient, mixed $appointmentId): ?Appointment
    {
        if ($appointmentId === null) {
            return null;
        }

        $appointment = Appointment::query()
            ->where('company_id', $companyId)
            ->where('patient_id', $patient->id)
            ->whereKey((int) $appointmentId)
            ->first();

        if (! $appointment) {
            throw ValidationException::withMessages([
                'appointment_id' => [__('errors.payment_appointment_invalid')],
            ]);
        }

        return $appointment;
    }

    private function interventionForPatient(int $companyId, Patient $patient, mixed $interventionId): ?Intervention
    {
        if ($interventionId === null) {
            return null;
        }

        $intervention = Intervention::query()
            ->where('company_id', $companyId)
            ->where('patient_id', $patient->id)
            ->whereKey((int) $interventionId)
            ->first();

        if (! $intervention) {
            throw ValidationException::withMessages([
                'intervention_id' => [__('errors.payment_intervention_invalid')],
            ]);
        }

        return $intervention;
    }

    private function paymentType(?Appointment $appointment, ?Intervention $intervention): string
    {
        if ($intervention) {
            return PatientPayment::TYPE_INTERVENTION;
        }

        if ($appointment) {
            return PatientPayment::TYPE_APPOINTMENT;
        }

        return PatientPayment::TYPE_CUSTOM;
    }

    private function defaultDescription(?Appointment $appointment, ?Intervention $intervention): string
    {
        if ($intervention) {
            return $intervention->title;
        }

        if ($appointment) {
            return 'Placanje termina';
        }

        return 'Dentio placanje';
    }

    private function companyIdOrFail(User $actor): int
    {
        if (! $actor->company_id) {
            throw new MissingCompanyContextException(__('errors.missing_company'));
        }

        return (int) $actor->company_id;
    }
}
