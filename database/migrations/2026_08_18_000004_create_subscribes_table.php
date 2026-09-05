<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Unified `subscribes` table (Subscribe model, Laravel convention).
     *
     * Holds all subscription types — product subscriptions and opt-in
     * subscriptions (post notifications, announcements, etc.).
     */
    public function up(): void
    {
        Schema::create('subscribes', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('type', 50)->default('product'); // product | post | announcement | ...
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->cascadeOnDelete();
            $table->morphs('subscribable'); // User / Organization
            $table->foreignId('product_id')->nullable()->constrained('products')->cascadeOnDelete();
            $table->foreignId('variant_id')->nullable()->constrained('variants')->nullOnDelete();
            $table->decimal('quantity', 15, 3)->default(1);
            $table->decimal('amount', 15, 2)->default(0); // price per cycle
            $table->string('currency', 3)->default('USD');
            $table->foreignId('contract_id')->nullable()->constrained('contracts')->nullOnDelete();
            $table->string('frequency')->default('monthly'); // weekly | monthly | quarterly | yearly
            $table->unsignedInteger('interval')->default(1);
            $table->json('shipping_address')->nullable();
            $table->json('billing_address')->nullable();
            $table->string('payment_method')->default('cod');
            $table->string('status')->default('active'); // active | paused | cancelled | expired
            $table->boolean('is_subscribed')->default(true);
            $table->timestamp('subscribed_at')->nullable();
            $table->timestamp('unsubscribed_at')->nullable();
            $table->string('unsubscribe_token')->nullable();
            $table->index('unsubscribe_token');
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('next_run_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('tenant_id');
            $table->index(['status', 'next_run_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscribes');
    }
};
