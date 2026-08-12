<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            if (! Schema::hasColumn('subscriptions', 'plan')) {
                $table->string('plan')->default('annual')->after('status');
            }
            if (! Schema::hasColumn('subscriptions', 'price_cents')) {
                $table->unsignedInteger('price_cents')->default(29900)->after('plan');
            }
            if (! Schema::hasColumn('subscriptions', 'currency')) {
                $table->string('currency', 3)->default('EUR')->after('price_cents');
            }
            if (! Schema::hasColumn('subscriptions', 'started_at')) {
                $table->timestamp('started_at')->nullable()->after('currency');
            }
            if (! Schema::hasColumn('subscriptions', 'canceled_at')) {
                $table->timestamp('canceled_at')->nullable()->after('current_period_end');
            }
            if (! Schema::hasColumn('subscriptions', 'ended_at')) {
                $table->timestamp('ended_at')->nullable()->after('canceled_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn(['plan', 'price_cents', 'currency', 'started_at', 'canceled_at', 'ended_at']);
        });
    }
};
