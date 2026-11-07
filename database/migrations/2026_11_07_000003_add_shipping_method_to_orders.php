<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Record which delivery option priced an order. The fee itself already lives in
 * `shipping_fee`; these columns make it auditable which zone/rate produced it,
 * so a later price change never rewrites history.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('shipping_method')->nullable()->after('shipping_fee');
            $table->uuid('shipping_rate_uuid')->nullable()->after('shipping_method');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['shipping_method', 'shipping_rate_uuid']);
        });
    }
};
