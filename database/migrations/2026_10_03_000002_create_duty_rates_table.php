<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('duty_rates', function (Blueprint $table) {
            $table->id();
            $table->string('hs_code');
            $table->string('description')->nullable();
            // Percentages (e.g. 10 for 10%). `china_fta_customs_duty` is the
            // reduced customs duty under the China–Pakistan FTA (CPFTA Phase II)
            // for China-origin goods; null means "no preferential rate".
            $table->decimal('customs_duty', 6, 2)->default(0);
            $table->decimal('additional_customs_duty', 6, 2)->default(0);
            $table->decimal('regulatory_duty', 6, 2)->default(0);
            $table->decimal('sales_tax', 6, 2)->default(0);
            $table->decimal('china_fta_customs_duty', 6, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('hs_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('duty_rates');
    }
};
