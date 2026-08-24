<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->string('payment_method'); // cod | jazzcash | easypaisa | upaisa | safepay
            $table->string('status')->default('pending'); // pending | authorized | paid | failed | cancelled | refunded
            $table->decimal('amount', 15, 2)->default(0);
            $table->string('currency', 3)->default('USD');
            $table->string('transaction_reference')->nullable();
            $table->json('gateway_payload')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('order_id');
            $table->index(['payment_method', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
