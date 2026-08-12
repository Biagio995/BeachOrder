<?php

use App\Models\Location;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('locations')) {
            return;
        }

        Location::query()->orderBy('id')->each(function (Location $location) {
            try {
                $code = Location::stableCode($location->type, $location->name);
            } catch (\InvalidArgumentException) {
                return;
            }

            if ($location->code === $code) {
                return;
            }

            $taken = Location::query()
                ->where('tenant_id', $location->tenant_id)
                ->where('code', $code)
                ->where('id', '!=', $location->id)
                ->exists();

            if ($taken) {
                return;
            }

            $location->update(['code' => $code]);
        });
    }

    public function down(): void
    {
        // Irreversible data normalization.
    }
};
