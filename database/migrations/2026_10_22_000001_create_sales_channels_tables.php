<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_channels', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 32)->unique();
            $table->string('name', 100);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('product_sales_channel', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sales_channel_id')->constrained('sales_channels')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['product_id', 'sales_channel_id']);
            $table->index(['sales_channel_id', 'product_id']);
        });

        $now = now();

        DB::table('sales_channels')->insert([
            ['code' => 'storefront', 'name' => 'Consumer storefront', 'is_active' => true, 'sort_order' => 10, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'punchout', 'name' => 'Punchout catalog', 'is_active' => true, 'sort_order' => 20, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'marketplace', 'name' => 'Marketplace', 'is_active' => true, 'sort_order' => 30, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'api', 'name' => 'API / partner', 'is_active' => true, 'sort_order' => 40, 'created_at' => $now, 'updated_at' => $now],
        ]);

        // Every existing product remains visible on the storefront after the
        // channel rollout. Punchout membership is opt-in.
        $storefrontId = DB::table('sales_channels')->where('code', 'storefront')->value('id');

        DB::table('products')
            ->select('id')
            ->orderBy('id')
            ->chunkById(500, function ($products) use ($storefrontId, $now): void {
                $rows = $products->map(fn ($product) => [
                    'product_id' => $product->id,
                    'sales_channel_id' => $storefrontId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->all();

                DB::table('product_sales_channel')->insertOrIgnore($rows);
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_sales_channel');
        Schema::dropIfExists('sales_channels');
    }
};
