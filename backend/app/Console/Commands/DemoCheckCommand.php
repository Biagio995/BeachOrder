<?php

namespace App\Console\Commands;

use App\Support\DemoMode;
use Illuminate\Console\Command;

class DemoCheckCommand extends Command
{
    protected $signature = 'demo:check
        {--fail : Exit with code 1 when demo-mode violations are found}';

    protected $description = 'Verify demo-mode safety (online payments off, printers/POS off)';

    public function handle(): int
    {
        if (! DemoMode::enabled()) {
            $this->info('DEMO_MODE is off — nothing to check.');

            return self::SUCCESS;
        }

        $violations = DemoMode::violations();

        $this->info('DEMO_MODE=true (online payments off, pay at location only)');
        $this->line('Printers: '.(DemoMode::printersEnabled() ? 'EXPLICITLY ENABLED' : 'disabled'));
        $this->line('POS: '.(DemoMode::posEnabled() ? 'EXPLICITLY ENABLED' : 'disabled'));

        foreach ($violations as $violation) {
            $this->warn($violation);
        }

        if ($this->option('fail') && $violations !== []) {
            return self::FAILURE;
        }

        if ($violations === []) {
            $this->info('Demo safety check passed.');
        }

        return self::SUCCESS;
    }
}
