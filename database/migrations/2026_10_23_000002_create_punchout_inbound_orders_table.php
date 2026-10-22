<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('punchout_inbound_orders', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('buyer_id')->nullable()->constrained('punchout_buyers')->nullOnDelete();

            // cXML payloadID is the buyer's idempotency key. A retry must not
            // create a second order, so it is unique per tenant.
            $table->string('payload_id', 255);
            $table->string('order_reference')->nullable();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->string('status', 30)->default('received');
            $table->string('protocol', 20)->default('cxml');
            $table->string('currency', 3)->nullable();
            $table->decimal('declared_total', 20, 6)->nullable();
            $table->json('payload')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'payload_id']);
            $table->index(['buyer_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('punchout_inbound_orders');
    }
};
