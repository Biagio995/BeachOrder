<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pos_integrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('provider', 40);
            $table->string('api_endpoint', 500)->nullable();
            $table->text('credentials')->nullable();
            $table->string('store_location_id', 120)->nullable();
            $table->json('extra_params')->nullable();
            $table->boolean('is_enabled')->default(false);
            $table->string('connection_status', 20)->default('disconnected');
            $table->timestamp('last_sync_at')->nullable();
            $table->timestamp('last_connection_test_at')->nullable();
            $table->text('last_connection_error')->nullable();
            $table->string('webhook_secret', 64)->nullable();
            $table->timestamps();
        });

        Schema::create('pos_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pos_integration_id')->constrained()->cascadeOnDelete();
            $table->string('entity_type', 30);
            $table->unsignedBigInteger('local_id');
            $table->string('external_id', 120);
            $table->string('external_sku', 120)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(
                ['tenant_id', 'pos_integration_id', 'entity_type', 'local_id'],
                'pos_mappings_entity_unique'
            );
            $table->index(['tenant_id', 'entity_type']);
        });

        Schema::create('pos_order_syncs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pos_integration_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('idempotency_key', 120)->unique();
            $table->string('external_order_id', 120)->nullable();
            $table->string('sync_status', 20)->default('pending');
            $table->unsignedTinyInteger('retry_count')->default(0);
            $table->unsignedTinyInteger('max_retries')->default(5);
            $table->timestamp('next_retry_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'sync_status']);
            $table->index(['sync_status', 'next_retry_at']);
        });

        Schema::create('pos_sync_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pos_order_sync_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('attempt_number');
            $table->string('status', 20);
            $table->text('error_message')->nullable();
            $table->json('response_summary')->nullable();
            $table->timestamp('attempted_at');
            $table->timestamps();

            $table->index(['pos_order_sync_id', 'attempt_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_sync_attempts');
        Schema::dropIfExists('pos_order_syncs');
        Schema::dropIfExists('pos_mappings');
        Schema::dropIfExists('pos_integrations');
    }
};
