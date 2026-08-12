<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\StripeSubscriptionService;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    public function __construct(
        private readonly StripeSubscriptionService $stripeSubscriptions,
    ) {}

    public function show(Request $request): JsonResponse
    {
        $tenant = TenantContext::require();

        return response()->json($this->stripeSubscriptions->publicPayloadForTenant($tenant));
    }

    public function checkout(Request $request): JsonResponse
    {
        if (! $this->stripeSubscriptions->isConfigured()) {
            return response()->json([
                'message' => 'Subscription billing is not configured on this server.',
            ], 503);
        }

        $tenant = TenantContext::require();
        $user = $request->user();

        try {
            $result = $this->stripeSubscriptions->createCheckoutSession($tenant, $user);

            return response()->json($result);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'message' => 'Unable to start checkout. Please try again later.',
            ], 502);
        }
    }

    public function portal(Request $request): JsonResponse
    {
        if (! $this->stripeSubscriptions->isConfigured()) {
            return response()->json([
                'message' => 'Subscription billing is not configured on this server.',
            ], 503);
        }

        $tenant = TenantContext::require();

        try {
            $result = $this->stripeSubscriptions->createPortalSession($tenant);

            return response()->json($result);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'message' => 'Unable to open billing portal. Please try again later.',
            ], 502);
        }
    }
}
