<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('staff_position', 20)->nullable()->after('role');
        });

        // Restore positions from legacy role names when still present.
        DB::table('users')->where('role', 'cook')->update([
            'role' => 'staff',
            'staff_position' => 'kitchen',
        ]);
        DB::table('users')->where('role', 'bartender')->update([
            'role' => 'staff',
            'staff_position' => 'bar',
        ]);
        DB::table('users')->where('role', 'waiter')->update([
            'role' => 'staff',
            'staff_position' => 'waiter',
        ]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('staff_position');
        });
    }
};
