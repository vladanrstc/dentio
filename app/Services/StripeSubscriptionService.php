<?php

namespace App\Services;

use App\Exceptions\MissingCompanyContextException;
use App\Exceptions\StripeConfigurationException;
use App\Mail\SubscriptionActivatedMail;
use App\Models\Company;
use App\Models\User;
use App\Services\Contracts\StripeGatewayInterface;
use App\Services\Contracts\StripeSubscriptionServiceInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;

class StripeSubscriptionService implements StripeSubscriptionServiceInterface
{
    public function __construct(
        private readonly StripeGatewayInterface $stripe,
    ) {}

    public function billingSummary(User $actor): array
    {
        $company = $this->companyForActor($actor);

        return [
            'payments_enabled' => (bool) $company->payments_enabled,
            'has_stripe_customer' => $company->stripe_customer_id !== null,
            'subscription_status' => $company->subscription_status,
            'subscription_plan' => $company->subscription_plan,
            'current_period_end' => $company->subscription_current_period_end?->toIso8601String(),
            'subscription_current_period_end' => $company->subscription_current_period_end?->toIso8601String(),
            'cancel_at_period_end' => (bool) $company->subscription_cancel_at_period_end,
        ];
    }

    public function createCheckout(User $actor, string $plan): array
    {
        $company = $this->companyForActor($actor);
        $priceId = $this->priceIdForPlan($plan);
        $this->ensureStripeSecretConfigured();
        $this->ensureCustomer($company);

        $frontendUrl = rtrim((string) config('app.frontend_url'), '/');
        $session = $this->stripe->createCheckoutSession([
            'mode' => 'subscription',
            'customer' => $company->stripe_customer_id,
            'line_items' => [[
                'price' => $priceId,
                'quantity' => 1,
            ]],
            'metadata' => [
                'company_id' => (string) $company->id,
                'plan' => $plan,
            ],
            'success_url' => $frontendUrl.'/billing/success?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => $frontendUrl.'/billing/cancel',
        ]);

        return [
            'checkout_url' => (string) ($session['url'] ?? ''),
        ];
    }

    public function createPortal(User $actor): array
    {
        $company = $this->companyForActor($actor);
        $this->ensureStripeSecretConfigured();
        $this->ensureCustomer($company);

        $frontendUrl = rtrim((string) config('app.frontend_url'), '/');
        $session = $this->stripe->createPortalSession([
            'customer' => $company->stripe_customer_id,
            'return_url' => $frontendUrl.'/billing',
        ]);

        return [
            'portal_url' => (string) ($session['url'] ?? ''),
        ];
    }

    public function handleWebhookEvent(array $event): void
    {
        $type = $event['type'] ?? null;
        $object = $event['data']['object'] ?? [];

        match ($type) {
            'checkout.session.completed' => $this->handleCheckoutCompleted($object),
            'customer.subscription.created',
            'customer.subscription.updated',
            'customer.subscription.deleted' => $this->handleSubscriptionChanged($object),
            'invoice.paid',
            'invoice.payment_failed' => $this->handleInvoiceChanged($object),
            default => null,
        };
    }

    private function handleCheckoutCompleted(array $session): void
    {
        if (($session['mode'] ?? null) !== 'subscription') {
            return;
        }

        $company = $this->companyFromMetadata($session['metadata'] ?? []);
        if (! $company) {
            return;
        }

        $previousStatus = $company->subscription_status;
        $periodEnd = $this->periodEndFromPayload($session);

        $data = [
            'stripe_customer_id' => $session['customer'] ?? $company->stripe_customer_id,
            'stripe_subscription_id' => $session['subscription'] ?? $company->stripe_subscription_id,
            'subscription_status' => $session['status'] ?? $company->subscription_status,
            'subscription_plan' => $session['metadata']['plan'] ?? $company->subscription_plan,
        ];

        if ($periodEnd) {
            $data['subscription_current_period_end'] = $periodEnd;
        }

        $company->forceFill($data)->save();
        $this->sendActivatedMailIfNeeded($company->refresh(), $previousStatus);
    }

    private function handleSubscriptionChanged(array $subscription): void
    {
        $company = $this->companyFromSubscription($subscription);
        if (! $company) {
            return;
        }

        $previousStatus = $company->subscription_status;
        $periodEnd = $this->periodEndFromPayload($subscription);

        $data = [
            'stripe_subscription_id' => $subscription['id'] ?? $company->stripe_subscription_id,
            'stripe_customer_id' => $subscription['customer'] ?? $company->stripe_customer_id,
            'subscription_status' => $subscription['status'] ?? $company->subscription_status,
            'subscription_plan' => $subscription['metadata']['plan'] ?? $company->subscription_plan,
            'subscription_cancel_at_period_end' => (bool) ($subscription['cancel_at_period_end'] ?? false),
        ];

        if ($periodEnd) {
            $data['subscription_current_period_end'] = $periodEnd;
        }

        $company->forceFill($data)->save();
        $this->sendActivatedMailIfNeeded($company->refresh(), $previousStatus);
    }

    private function handleInvoiceChanged(array $invoice): void
    {
        $subscriptionId = $invoice['subscription'] ?? null;
        $customerId = $invoice['customer'] ?? null;

        if (! $subscriptionId && ! $customerId) {
            return;
        }

        $company = Company::query()
            ->where(function ($query) use ($subscriptionId, $customerId): void {
                if ($subscriptionId) {
                    $query->where('stripe_subscription_id', $subscriptionId);
                }

                if ($customerId) {
                    $method = $subscriptionId ? 'orWhere' : 'where';
                    $query->{$method}('stripe_customer_id', $customerId);
                }
            })
            ->first();

        if (! $company) {
            return;
        }

        $previousStatus = $company->subscription_status;
        $periodEnd = $this->periodEndFromPayload($invoice);

        $data = [
            'subscription_status' => ($invoice['paid'] ?? false) ? 'active' : 'past_due',
        ];

        if ($periodEnd) {
            $data['subscription_current_period_end'] = $periodEnd;
        }

        $company->forceFill($data)->save();
        $this->sendActivatedMailIfNeeded($company->refresh(), $previousStatus);
    }

    private function companyFromSubscription(array $subscription): ?Company
    {
        $subscriptionId = $subscription['id'] ?? null;
        $customerId = $subscription['customer'] ?? null;

        if (! $subscriptionId && ! $customerId) {
            return null;
        }

        return Company::query()
            ->where(function ($query) use ($subscriptionId, $customerId): void {
                if ($subscriptionId) {
                    $query->where('stripe_subscription_id', $subscriptionId);
                }

                if ($customerId) {
                    $method = $subscriptionId ? 'orWhere' : 'where';
                    $query->{$method}('stripe_customer_id', $customerId);
                }
            })
            ->first();
    }

    private function companyFromMetadata(array $metadata): ?Company
    {
        $companyId = $metadata['company_id'] ?? null;

        return $companyId ? Company::query()->find((int) $companyId) : null;
    }

    private function ensureCustomer(Company $company): void
    {
        if ($company->stripe_customer_id !== null) {
            return;
        }

        $customer = $this->stripe->createCustomer([
            'name' => $company->name,
            'email' => $company->email,
            'metadata' => [
                'company_id' => (string) $company->id,
            ],
        ]);

        $company->forceFill([
            'stripe_customer_id' => $customer['id'] ?? null,
        ])->save();
    }

    private function priceIdForPlan(string $plan): string
    {
        $priceId = match ($plan) {
            'monthly' => config('services.stripe.monthly_price_id'),
            'yearly' => config('services.stripe.yearly_price_id'),
            default => null,
        };

        if (! is_string($priceId) || $priceId === '') {
            throw new StripeConfigurationException(__('errors.stripe_not_configured'));
        }

        return $priceId;
    }

    private function ensureStripeSecretConfigured(): void
    {
        $secret = config('services.stripe.secret');

        if (! is_string($secret) || trim($secret) === '') {
            throw new StripeConfigurationException(__('errors.stripe_not_configured'));
        }
    }

    private function companyForActor(User $actor): Company
    {
        $actor->loadMissing('company');

        if (! $actor->company) {
            throw new MissingCompanyContextException(__('errors.missing_company'));
        }

        return $actor->company;
    }

    private function timestampOrNull(mixed $timestamp): ?Carbon
    {
        if (! is_numeric($timestamp)) {
            return null;
        }

        return Carbon::createFromTimestamp((int) $timestamp);
    }

    private function periodEndFromPayload(array $payload): ?Carbon
    {
        return $this->timestampOrNull($payload['current_period_end'] ?? null)
            ?? $this->timestampOrNull($payload['period_end'] ?? null)
            ?? $this->timestampOrNull($payload['subscription_details']['current_period_end'] ?? null)
            ?? $this->timestampOrNull($payload['lines']['data'][0]['period']['end'] ?? null);
    }

    private function sendActivatedMailIfNeeded(Company $company, ?string $previousStatus): void
    {
        if ($previousStatus === 'active' || $company->subscription_status !== 'active') {
            return;
        }

        $recipient = $company->users()
            ->where('role', User::ROLE_COMPANY_ADMIN)
            ->whereNotNull('email')
            ->orderBy('id')
            ->first();

        if (! $recipient?->email) {
            return;
        }

        Mail::to($recipient->email)->send(new SubscriptionActivatedMail($company));
    }
}
