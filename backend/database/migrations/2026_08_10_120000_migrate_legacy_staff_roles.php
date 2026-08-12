<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')
            ->whereIn('role', ['cook', 'bartender', 'waiter'])
            ->update(['role' => 'staff']);
    }

    public function down(): void
    {
        // Legacy roles cannot be restored reliably.
    }
};
