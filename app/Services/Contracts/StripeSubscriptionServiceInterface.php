<?php

namespace App\Services\Contracts;

use App\Models\User;

interface StripeSubscriptionServiceInterface
{
    /**
     * @return array<string, mixed>
     */
    public function billingSummary(User $actor): array;

    /**
     * @return array<string, string>
     */
    public function createCheckout(User $actor, string $plan): array;

    /**
     * @return array<string, string>
     */
    public function createPortal(User $actor): array;

    /**
     * @param  array<string, mixed>  $event
     */
    public function handleWebhookEvent(array $event): void;
}
