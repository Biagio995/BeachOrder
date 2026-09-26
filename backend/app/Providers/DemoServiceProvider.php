<?php

namespace App\Providers;

use App\Support\DemoMode;
use Illuminate\Support\ServiceProvider;

class DemoServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Harden runtime integrations while DEMO_MODE=true.
     *
     * Printers/POS are forced off (config-level kill switch, in addition to
     * the per-service guards) so the demo never attempts a hardware or
     * external POS connection.
     */
    public function boot(): void
    {
        if (! DemoMode::enabled()) {
            return;
        }

        if (! DemoMode::printersEnabled()) {
            config(['demo.printers_enabled' => false]);
        }

        if (! DemoMode::posEnabled()) {
            config(['pos.force_stub' => true]);
        }

        // The demo menu is authored in Italian. Machine translation would
        // rewrite curated copy (stored 'it' is a secondary locale for the
        // translator) and adds an external HTTP dependency to every menu
        // request — unacceptable while filming. Serve stored text as-is.
        config(['translation.enabled' => false]);
    }
}
