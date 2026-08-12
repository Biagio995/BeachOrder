<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_variant_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->json('name');
            $table->boolean('is_required')->default(true);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['product_id', 'sort_order']);
        });

        Schema::create('product_variant_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_group_id')->constrained('product_variant_groups')->cascadeOnDelete();
            $table->json('name');
            $table->decimal('price', 10, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['product_variant_group_id', 'sort_order'], 'variant_options_group_sort_idx');
        });

        Schema::create('product_addon_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->json('name');
            $table->unsignedInteger('min_selections')->default(0);
            $table->unsignedInteger('max_selections')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['product_id', 'sort_order']);
        });

        Schema::table('product_addons', function (Blueprint $table) {
            $table->foreignId('product_addon_group_id')
                ->nullable()
                ->after('product_id')
                ->constrained('product_addon_groups')
                ->cascadeOnDelete();
            $table->unsignedInteger('min_quantity')->default(0)->after('is_active');
            $table->unsignedInteger('max_quantity')->default(1)->after('min_quantity');
        });

        // Move existing flat addons into a default group per product.
        $addons = DB::table('product_addons')->whereNull('product_addon_group_id')->orderBy('id')->get();
        $groupIdsByProduct = [];

        foreach ($addons as $addon) {
            $productId = (int) $addon->product_id;
            if (! isset($groupIdsByProduct[$productId])) {
                $groupIdsByProduct[$productId] = DB::table('product_addon_groups')->insertGetId([
                    'tenant_id' => $addon->tenant_id,
                    'product_id' => $productId,
                    'name' => json_encode([
                        'en' => 'Extras',
                        'el' => 'Extras',
                        'it' => 'Extra',
                        'de' => 'Extras',
                    ]),
                    'min_selections' => 0,
                    'max_selections' => null,
                    'is_active' => true,
                    'sort_order' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::table('product_addons')->where('id', $addon->id)->update([
                'product_addon_group_id' => $groupIdsByProduct[$productId],
                'min_quantity' => 0,
                'max_quantity' => 1,
            ]);
        }

        Schema::table('order_items', function (Blueprint $table) {
            $table->json('variants')->nullable()->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn('variants');
        });

        Schema::table('product_addons', function (Blueprint $table) {
            $table->dropConstrainedForeignId('product_addon_group_id');
            $table->dropColumn(['min_quantity', 'max_quantity']);
        });

        Schema::dropIfExists('product_addon_groups');
        Schema::dropIfExists('product_variant_options');
        Schema::dropIfExists('product_variant_groups');
    }
};
