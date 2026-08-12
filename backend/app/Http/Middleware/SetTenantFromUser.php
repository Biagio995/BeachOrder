<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Support\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetTenantFromUser
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        if (! $user->is_active) {
            $user->currentAccessToken()?->delete();

            return response()->json(['message' => 'Account disabled'], 403);
        }

        $requestedSlug = $this->requestedTenantSlug($request);

        if ($user->isSuperAdmin()) {
            // Platform tenant CRUD can run without a tenant context.
            if ($request->is('api/platform/*')) {
                TenantContext::clear();

                return $next($request);
            }

            if (! $requestedSlug) {
                return response()->json(['message' => 'X-Tenant header required'], 400);
            }

            $tenant = Tenant::query()->where('slug', $requestedSlug)->first();
            if (! $tenant) {
                return response()->json(['message' => 'Tenant not found'], 404);
            }

            if (! $user->canAccessTenant($tenant)) {
                return response()->json(['message' => 'Tenant access denied'], 403);
            }

            TenantContext::set($tenant);
            $request->attributes->set('tenant', $tenant);

            return $next($request);
        }

        if (! $user->tenant_id) {
            return response()->json(['message' => 'User has no tenant'], 403);
        }

        $tenant = $user->tenant()->first();
        if (! $tenant) {
            return response()->json(['message' => 'User has no tenant'], 403);
        }

        if ($tenant->isAdminSuspended()) {
            return response()->json([
                'message' => 'Tenant inactive',
                'code' => 'tenant_inactive',
            ], 403);
        }

        // Defense in depth: reject spoofed X-Tenant that does not match membership.
        if ($requestedSlug && $requestedSlug !== $tenant->slug) {
            return response()->json(['message' => 'Tenant access denied'], 403);
        }

        if (! $user->canAccessTenant($tenant)) {
            return response()->json(['message' => 'Tenant access denied'], 403);
        }

        TenantContext::set($tenant);
        $request->attributes->set('tenant', $tenant);

        return $next($request);
    }

    private function requestedTenantSlug(Request $request): ?string
    {
        $slug = $request->header('X-Tenant') ?: $request->query('tenant');

        return is_string($slug) && $slug !== '' ? $slug : null;
    }
}
