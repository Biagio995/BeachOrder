<?php

namespace App\Console\Commands;

use App\Support\DemoMode;
use Illuminate\Console\Command;

class DemoCheckCommand extends Command
{
    protected $signature = 'demo:check
        {--fail : Exit with code 1 when demo-mode violations are found}';

    protected $description = 'Verify demo-mode safety (Stripe test-only, printers/POS off)';

    public function handle(): int
    {
        if (! DemoMode::enabled()) {
            $this->info('DEMO_MODE is off — nothing to check.');

            return self::SUCCESS;
        }

        $violations = DemoMode::violations();

        $this->info('DEMO_MODE=true');
        $this->line('Stripe keys: '.(DemoMode::stripeKeysAreTestOnly() ? 'OK (test-only or empty)' : 'LIVE KEYS DETECTED'));
        $this->line('Printers: '.(DemoMode::printersEnabled() ? 'EXPLICITLY ENABLED' : 'disabled'));
        $this->line('POS: '.(DemoMode::posEnabled() ? 'EXPLICITLY ENABLED' : 'disabled'));

        if ($violations === []) {
            $this->info('Demo safety check passed.');

            return self::SUCCESS;
        }

        foreach ($violations as $violation) {
            // Live keys are a hard failure; explicit hardware opt-ins are warnings.
            if (str_contains($violation, 'Live Stripe keys')) {
                $this->error($violation);
            } else {
                $this->warn($violation);
            }
        }

        if ($this->option('fail') && ! DemoMode::stripeKeysAreTestOnly()) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
