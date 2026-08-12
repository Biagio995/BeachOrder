<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('station', 20)->default('kitchen')->after('category_id');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->string('station', 20)->default('kitchen')->after('product_id');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->string('kitchen_status', 20)->nullable()->after('status');
            $table->string('bar_status', 20)->nullable()->after('kitchen_status');
        });

        // Heuristic backfill: drink-like categories → bar, everything else → kitchen.
        if (Schema::hasTable('categories') && Schema::hasTable('products')) {
            $drinkSlugs = ['bevande', 'drinks', 'bar', 'cocktail', 'cocktails', 'bibite'];
            $categoryIds = DB::table('categories')
                ->whereIn('slug', $drinkSlugs)
                ->pluck('id');

            if ($categoryIds->isNotEmpty()) {
                DB::table('products')->whereIn('category_id', $categoryIds)->update(['station' => 'bar']);
            }

            DB::table('order_items')
                ->whereIn('product_id', function ($query) {
                    $query->select('id')->from('products')->where('station', 'bar');
                })
                ->update(['station' => 'bar']);
        }

        // Sync station statuses for open orders from item stations.
        if (Schema::hasTable('orders')) {
            $open = DB::table('orders')
                ->whereIn('status', ['received', 'accepted', 'preparing', 'ready'])
                ->get(['id', 'status']);

            foreach ($open as $order) {
                $stations = DB::table('order_items')
                    ->where('order_id', $order->id)
                    ->distinct()
                    ->pluck('station');

                $status = $order->status === 'ready' ? 'ready' : $order->status;

                DB::table('orders')->where('id', $order->id)->update([
                    'kitchen_status' => $stations->contains('kitchen') ? $status : null,
                    'bar_status' => $stations->contains('bar') ? $status : null,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['kitchen_status', 'bar_status']);
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn('station');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('station');
        });
    }
};
