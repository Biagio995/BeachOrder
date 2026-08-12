<?php

namespace App\Jobs;

use App\Models\PosOrderSync;
use App\Services\Pos\PosOrderSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncOrderToPosJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(
        public int $posOrderSyncId,
    ) {}

    public function handle(PosOrderSyncService $syncService): void
    {
        $sync = PosOrderSync::query()->find($this->posOrderSyncId);
        if (! $sync) {
            return;
        }

        $syncService->processSync($sync);
    }
}
