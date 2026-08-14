<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('seller_sku')->nullable()->after('sku');
        });

        // The system-generated `sku` must be unique per tenant (was a plain index).
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['sku']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->unique(['tenant_id', 'sku']);
            $table->unique(['tenant_id', 'seller_sku']);
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropUnique(['tenant_id', 'seller_sku']);
            $table->dropUnique(['tenant_id', 'sku']);
            $table->index('sku');
            $table->dropColumn('seller_sku');
        });
    }
};
