<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('sourcing', 20)->default('in_stock')->after('model_number');
            $table->unsignedInteger('lead_time_days')->nullable()->after('sourcing');
            $table->string('origin_country', 2)->nullable()->after('lead_time_days');
            $table->string('sourcing_url', 2048)->nullable()->after('origin_country');
            $table->string('supplier_reference', 100)->nullable()->after('sourcing_url');

            $table->index('sourcing');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['sourcing']);
            $table->dropColumn([
                'sourcing', 'lead_time_days', 'origin_country', 'sourcing_url', 'supplier_reference',
            ]);
        });
    }
};
