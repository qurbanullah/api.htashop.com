<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * GTIN is the GS1 global trade item number — an external identifier that stays
 * the same whoever sells the product. It is separate from the platform SKU
 * (HTASIN), which is private and internal.
 *
 * Stored as a string because a 14-digit number exceeds 32-bit ints and leading
 * zeros are significant.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('gtin', 14)->nullable()->after('barcode');
            $table->index('gtin');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['gtin']);
            $table->dropColumn('gtin');
        });
    }
};
