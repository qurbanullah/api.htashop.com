<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One import file carries one kind of record (products, variants or images).
 *
 * `warnings` separates *non-blocking* notes from `errors`: a product row whose
 * remote image could not be downloaded still imports, but the operator must be
 * able to see why the picture is missing.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_imports', function (Blueprint $table) {
            $table->string('kind', 20)->default('products')->after('format');
            $table->index(['tenant_id', 'kind']);
        });

        Schema::table('product_import_rows', function (Blueprint $table) {
            $table->json('warnings')->nullable()->after('errors');
        });
    }

    public function down(): void
    {
        Schema::table('product_import_rows', function (Blueprint $table) {
            $table->dropColumn('warnings');
        });

        Schema::table('product_imports', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'kind']);
            $table->dropColumn('kind');
        });
    }
};
