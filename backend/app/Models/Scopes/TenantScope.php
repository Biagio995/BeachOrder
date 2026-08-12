<?php

namespace App\Models\Scopes;

use App\Support\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Apply tenant_id filter when middleware has established TenantContext.
 * Context must be set only after verifying the caller may access that tenant.
 */
class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        if (TenantContext::id()) {
            $builder->where($model->getTable().'.tenant_id', TenantContext::id());
        }
    }
}
