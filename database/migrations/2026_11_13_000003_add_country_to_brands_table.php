<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Brand country of origin, mirroring `manufacturers.country`.
 *
 * `brands.origin` already exists but means provenance (`admin` or `vendor`),
 * so country needs its own column.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('brands', function (Blueprint $table) {
            $table->string('country')->nullable()->after('slug');
        });
    }

    public function down(): void
    {
        Schema::table('brands', function (Blueprint $table) {
            $table->dropColumn('country');
        });
    }
};
