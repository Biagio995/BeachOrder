<?php

namespace App\Support;

use App\Models\Tenant;
use Illuminate\Notifications\Messages\MailMessage;

class TenantMailMessage
{
    public static function salutation(?Tenant $tenant): string
    {
        if ($tenant?->name) {
            return __('Il team :tenant', ['tenant' => $tenant->name]);
        }

        return __('Il team BeachOrder');
    }

    public static function apply(MailMessage $mail, ?Tenant $tenant): MailMessage
    {
        $branding = TenantBranding::resolve($tenant?->branding);
        $primary = $branding['primary_color'] ?? null;

        if (is_string($primary) && preg_match('/^#[0-9a-fA-F]{6}$/', $primary)) {
            $mail->theme($primary);
        }

        return $mail->salutation(self::salutation($tenant));
    }
}
