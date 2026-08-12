<?php

namespace App\Http\Middleware;

use App\Services\StripeSubscriptionService;
use App\Support\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantSubscriptionActive
{
    public function __construct(
        private readonly StripeSubscriptionService $stripeSubscriptions,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || $user->isSuperAdmin()) {
            return $next($request);
        }

        $tenant = TenantContext::get();
        if (! $tenant || $tenant->isDemo()) {
            return $next($request);
        }

        $payload = $this->stripeSubscriptions->publicPayloadForTenant($tenant);

        if (($payload['grants_access'] ?? false) === true) {
            return $next($request);
        }

        return response()->json([
            'message' => 'An active subscription is required to use this feature.',
            'subscription' => $payload,
        ], 402);
    }
}
