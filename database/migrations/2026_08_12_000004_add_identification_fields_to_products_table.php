<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('sku')->nullable()->after('slug');
            $table->string('part_number')->nullable()->after('sku');
            $table->string('hs_code')->nullable()->after('part_number');
            $table->string('unspsc')->nullable()->after('hs_code');
            $table->string('ntn')->nullable()->after('unspsc');
            $table->string('barcode')->nullable()->after('ntn');
            $table->string('model_number')->nullable()->after('barcode');
            $table->string('manufacturer')->nullable()->after('model_number');

            $table->index('sku');
            $table->index('part_number');
            $table->index('hs_code');
            $table->index('unspsc');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn([
                'sku', 'part_number', 'hs_code', 'unspsc', 'ntn',
                'barcode', 'model_number', 'manufacturer',
            ]);
        });
    }
};
