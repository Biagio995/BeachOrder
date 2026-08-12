<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->unsignedInteger('stock_quantity')->nullable()->after('prep_time_minutes');
            $table->boolean('track_inventory')->default(false)->after('stock_quantity');
            $table->unsignedInteger('low_stock_threshold')->nullable()->after('track_inventory');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->string('payment_method')->default('pay_at_location')->after('total');
            // pay_at_location | card_online | apple_pay | google_pay
            $table->string('payment_status')->default('unpaid')->after('payment_method');
            // unpaid | pending | paid | failed | refunded
            $table->string('payment_reference')->nullable()->after('payment_status');
            $table->unsignedInteger('loyalty_points_earned')->default(0)->after('payment_reference');
        });

        Schema::create('loyalty_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('customer_session');
            $table->string('customer_name')->nullable();
            $table->unsignedInteger('points')->default(0);
            $table->timestamps();

            $table->unique(['tenant_id', 'customer_session']);
        });

        Schema::create('loyalty_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('loyalty_account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->integer('points');
            $table->string('reason');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loyalty_transactions');
        Schema::dropIfExists('loyalty_accounts');

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['payment_method', 'payment_status', 'payment_reference', 'loyalty_points_earned']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['stock_quantity', 'track_inventory', 'low_stock_threshold']);
        });
    }
};
