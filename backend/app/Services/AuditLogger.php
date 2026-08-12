<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Support\LogRedactor;
use App\Support\TenantContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class AuditLogger
{
    public static function log(string $action, ?Model $model = null, ?array $old = null, ?array $new = null): void
    {
        AuditLog::create([
            'tenant_id' => ($model !== null && isset($model->tenant_id))
                ? $model->tenant_id
                : TenantContext::id(),
            'user_id' => Auth::id(),
            'action' => $action,
            'auditable_type' => $model ? $model::class : null,
            'auditable_id' => $model?->getKey(),
            'old_values' => LogRedactor::redact($old),
            'new_values' => LogRedactor::redact($new),
            'ip_address' => LogRedactor::maskPii((string) Request::ip()),
        ]);
    }
}
