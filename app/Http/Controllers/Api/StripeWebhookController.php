<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Contracts\StripeGatewayInterface;
use App\Services\Contracts\StripePaymentServiceInterface;
use App\Services\Contracts\StripeSubscriptionServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StripeWebhookController extends Controller
{
    public function __construct(
        private readonly StripeGatewayInterface $stripe,
        private readonly StripePaymentServiceInterface $payments,
        private readonly StripeSubscriptionServiceInterface $subscriptions,
    ) {}

    public function handle(Request $request): JsonResponse
    {
        $event = $this->stripe->constructWebhookEvent(
            $request->getContent(),
            $request->header('Stripe-Signature'),
        );

        $this->payments->handleWebhookEvent($event);
        $this->subscriptions->handleWebhookEvent($event);

        return response()->json([
            'data' => [
                'received' => true,
            ],
        ]);
    }
}
