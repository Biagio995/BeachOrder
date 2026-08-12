<?php

namespace App\Console\Commands;

use App\Services\DataRetentionService;
use Illuminate\Console\Command;

class PurgeExpiredPersonalData extends Command
{
    protected $signature = 'privacy:purge';

    protected $description = 'Purge or anonymize personal data according to retention policy';

    public function handle(DataRetentionService $retention): int
    {
        $this->info('Running privacy retention purge…');

        $results = $retention->purgeAll();

        foreach ($results as $key => $count) {
            $this->line("  {$key}: {$count}");
        }

        $this->info('Done.');

        return self::SUCCESS;
    }
}
