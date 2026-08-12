<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Support\TenantBranding;
use App\Support\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetTenantFromRoute
{
    public function handle(Request $request, Closure $next): Response
    {
        $slug = $request->route('tenant');

        if (! $slug) {
            return response()->json(['message' => 'Tenant required'], 400);
        }

        $tenant = Tenant::query()->where('slug', $slug)->first();

        if (! $tenant) {
            return response()->json(['message' => 'Tenant not found'], 404);
        }

        if (! $tenant->is_active) {
            return response()->json([
                'message' => 'Service temporarily unavailable',
                'code' => 'tenant_inactive',
                'tenant' => [
                    'name' => $tenant->name,
                    'restaurant_name' => $tenant->name,
                    'slug' => $tenant->slug,
                    'branding' => TenantBranding::resolve($tenant->branding),
                ],
            ], 403);
        }

        TenantContext::set($tenant);
        $request->attributes->set('tenant', $tenant);

        return $next($request);
    }
}
