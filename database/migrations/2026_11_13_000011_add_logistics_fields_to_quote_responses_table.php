<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quote_responses', function (Blueprint $table): void {
            $table->string('incoterm', 3)->nullable();
            $table->string('incoterm_location', 120)->nullable();
            $table->string('payment_terms', 32)->nullable();
            $table->unsignedSmallInteger('warranty_months')->nullable();
            $table->string('shipping_method', 30)->nullable();
            $table->string('country_of_origin', 64)->nullable();
            $table->string('hs_code', 20)->nullable();
            $table->unsignedSmallInteger('lead_time_days')->nullable();
            $table->date('estimated_ship_date')->nullable();
            $table->string('port_of_loading', 120)->nullable();
            $table->string('port_of_discharge', 120)->nullable();
            $table->boolean('partial_shipment_allowed')->default(true);
            $table->text('shipping_notes')->nullable();
            $table->unsignedSmallInteger('package_count')->nullable();
            $table->decimal('gross_weight_kg', 10, 3)->nullable();
            $table->decimal('net_weight_kg', 10, 3)->nullable();
            $table->string('package_dimensions', 120)->nullable();
            $table->text('packaging_notes')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('quote_responses', function (Blueprint $table): void {
            $table->dropColumn([
                'incoterm',
                'incoterm_location',
                'payment_terms',
                'warranty_months',
                'shipping_method',
                'country_of_origin',
                'hs_code',
                'lead_time_days',
                'estimated_ship_date',
                'port_of_loading',
                'port_of_discharge',
                'partial_shipment_allowed',
                'shipping_notes',
                'package_count',
                'gross_weight_kg',
                'net_weight_kg',
                'package_dimensions',
                'packaging_notes',
            ]);
        });
    }
};
