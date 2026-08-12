<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('tenant.{tenantId}.orders', function (?User $user, int|string $tenantId): bool {
    return (bool) $user?->canAccessTenant((int) $tenantId);
});

Broadcast::channel('tenant.{tenantId}.waiter-calls', function (?User $user, int|string $tenantId): bool {
    return (bool) $user?->canAccessTenant((int) $tenantId);
});

Broadcast::channel('tenant.{tenantId}.location.{locationId}', function (?User $user, int|string $tenantId): bool {
    return (bool) $user?->canAccessTenant((int) $tenantId);
});

// Public customer tracking channel — auth not required (order id + session gate the API).
Broadcast::channel('tenant.{tenantId}.orders.{orderId}', fn () => true);
