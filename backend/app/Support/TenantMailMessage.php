<?php

namespace App\Support;

use App\Models\Tenant;
use Illuminate\Notifications\Messages\MailMessage;

class TenantMailMessage
{
    public static function salutation(?Tenant $tenant, bool $platform = false): string
    {
        if (! $platform && $tenant?->name) {
            return __('Il team :tenant', ['tenant' => $tenant->name]);
        }

        return __('Il team Ordequi');
    }

    public static function apply(MailMessage $mail, ?Tenant $tenant, bool $platform = false): MailMessage
    {
        $branding = TenantBranding::resolve($platform ? null : $tenant?->branding);
        $primary = $branding['primary_color'] ?? null;

        // Laravel theme() is a markdown view name, not a hex color.
        if (is_string($primary) && preg_match('/^[a-zA-Z][\w-]*$/', $primary)) {
            $mail->theme($primary);
        }

        return $mail->salutation(self::salutation($tenant, $platform));
    }
}
