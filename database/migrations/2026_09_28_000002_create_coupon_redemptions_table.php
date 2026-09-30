<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Append-only record of who used what. `used_count` on the coupon is
        // the fast path for the usage limit; this table is the source of truth
        // and what lets a cancellation give the customer their use back.
        Schema::create('coupon_redemptions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->foreignId('coupon_id')->constrained('coupons')->cascadeOnDelete();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            // Guests are identified by their cart session, so a per-customer
            // limit still applies to them.
            $table->string('session_id')->nullable();

            $table->string('code');
            $table->decimal('amount', 15, 2)->default(0);
            $table->string('currency', 3)->default('PKR');
            $table->timestamps();

            // One redemption per coupon per order, whatever the retry does.
            $table->unique(['coupon_id', 'order_id']);
            $table->index(['coupon_id', 'user_id']);
            $table->index(['coupon_id', 'session_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coupon_redemptions');
    }
};
