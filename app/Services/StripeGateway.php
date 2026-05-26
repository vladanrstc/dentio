<?php

namespace App\Services;

use App\Exceptions\StripeConfigurationException;
use App\Exceptions\StripeWebhookException;
use App\Services\Contracts\StripeGatewayInterface;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Stripe\Exception\ApiErrorException;
use Stripe\Exception\SignatureVerificationException;
use Stripe\StripeClient;
use Stripe\Webhook;
use UnexpectedValueException;

class StripeGateway implements StripeGatewayInterface
{
    private ?StripeClient $client = null;

    public function createCheckoutSession(array $params): array
    {
        try {
            return $this->client()->checkout->sessions->create($params)->toArray();
        } catch (ApiErrorException|InvalidArgumentException $exception) {
            $this->logStripeException($exception);
            throw new StripeConfigurationException(__('errors.stripe_not_configured'));
        }
    }

    public function createCustomer(array $params): array
    {
        try {
            return $this->client()->customers->create($params)->toArray();
        } catch (ApiErrorException|InvalidArgumentException $exception) {
            $this->logStripeException($exception);
            throw new StripeConfigurationException(__('errors.stripe_not_configured'));
        }
    }

    public function createPortalSession(array $params): array
    {
        try {
            return $this->client()->billingPortal->sessions->create($params)->toArray();
        } catch (ApiErrorException|InvalidArgumentException $exception) {
            $this->logStripeException($exception);
            throw new StripeConfigurationException(__('errors.stripe_not_configured'));
        }
    }

    public function constructWebhookEvent(string $payload, ?string $signature): array
    {
        try {
            $event = Webhook::constructEvent(
                $payload,
                (string) $signature,
                (string) config('services.stripe.webhook_secret')
            );
        } catch (UnexpectedValueException|SignatureVerificationException) {
            throw new StripeWebhookException(__('errors.stripe_webhook_invalid'));
        }

        return $event->toArray();
    }

    public function retrieveSubscription(string $subscriptionId): array
    {
        try {
            return $this->client()->subscriptions->retrieve($subscriptionId)->toArray();
        } catch (ApiErrorException|InvalidArgumentException $exception) {
            $this->logStripeException($exception);
            throw new StripeConfigurationException(__('errors.stripe_not_configured'));
        }
    }

    private function client(): StripeClient
    {
        $secret = config('services.stripe.secret');
        if (! is_string($secret) || trim($secret) === '') {
            throw new StripeConfigurationException(__('errors.stripe_not_configured'));
        }

        return $this->client ??= new StripeClient((string) config('services.stripe.secret'));
    }

    private function logStripeException(ApiErrorException|InvalidArgumentException $exception): void
    {
        Log::error('Stripe API request failed.', [
            'message' => $exception->getMessage(),
            'exception' => $exception::class,
        ]);
    }
}
