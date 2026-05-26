<?php

namespace App\Services\Contracts;

interface StripeGatewayInterface
{
    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public function createCheckoutSession(array $params): array;

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public function createCustomer(array $params): array;

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public function createPortalSession(array $params): array;

    /**
     * @return array<string, mixed>
     */
    public function constructWebhookEvent(string $payload, ?string $signature): array;

    /**
     * @return array<string, mixed>
     */
    public function retrieveSubscription(string $subscriptionId): array;
}
