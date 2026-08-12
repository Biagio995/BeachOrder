<?php

namespace App\Support;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\Rules\Unique;

class TenantRules
{
    public static function exists(string $table, string $column = 'id'): Exists
    {
        return Rule::exists($table, $column)->where(
            fn ($query) => $query->where('tenant_id', TenantContext::id())
        );
    }

    public static function unique(string $table, string $column = 'slug'): Unique
    {
        return Rule::unique($table, $column)->where(
            fn ($query) => $query->where('tenant_id', TenantContext::id())
        );
    }
}
