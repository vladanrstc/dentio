<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Billing\CreateBillingCheckoutRequest;
use App\Services\Contracts\StripeSubscriptionServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CompanyBillingApiController extends Controller
{
    public function __construct(
        private readonly StripeSubscriptionServiceInterface $subscriptions,
    ) {}

    public function show(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->subscriptions->billingSummary($request->user()),
        ]);
    }

    public function checkout(CreateBillingCheckoutRequest $request): JsonResponse
    {
        return response()->json([
            'data' => $this->subscriptions->createCheckout(
                $request->user(),
                $request->validated('plan'),
            ),
        ]);
    }

    public function portal(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->subscriptions->createPortal($request->user()),
        ]);
    }
}
