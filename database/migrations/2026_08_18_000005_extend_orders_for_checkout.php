<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('id')->constrained('users')->nullOnDelete();
            $table->foreignId('organization_id')->nullable()->after('user_id')->constrained('organizations')->nullOnDelete();
            $table->string('source')->default('manual')->after('status'); // manual | subscription
            $table->foreignId('subscription_id')->nullable()->after('source')->constrained('subscribes')->nullOnDelete();
            $table->string('checkout_token')->nullable()->unique()->after('subscription_id');
            $table->string('customer_phone')->nullable()->after('customer_email');
            $table->decimal('subtotal', 15, 2)->default(0)->after('total_amount');
            $table->decimal('shipping_fee', 15, 2)->default(0)->after('subtotal');
            $table->decimal('tax', 15, 2)->default(0)->after('shipping_fee');
            $table->decimal('discount', 15, 2)->default(0)->after('tax');
            $table->json('shipping_address')->nullable()->after('discount');
            $table->json('billing_address')->nullable()->after('shipping_address');
            $table->text('notes')->nullable()->after('billing_address');
            $table->timestamp('placed_at')->nullable()->after('notes');
            $table->timestamp('confirmed_at')->nullable()->after('placed_at');

            $table->index(['user_id', 'status']);
            $table->index(['tenant_id', 'created_at']);
        });

        // Existing seeded orders get a sensible placed_at instead of NULL.
        DB::table('orders')->whereNull('placed_at')->update(['placed_at' => DB::raw('created_at')]);
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['subscription_id']);
            $table->dropForeign(['organization_id']);
            $table->dropForeign(['user_id']);
            $table->dropIndex(['tenant_id', 'created_at']);
            $table->dropIndex(['user_id', 'status']);
            $table->dropColumn([
                'user_id', 'organization_id', 'source', 'subscription_id', 'checkout_token',
                'customer_phone', 'subtotal', 'shipping_fee', 'tax', 'discount',
                'shipping_address', 'billing_address', 'notes', 'placed_at', 'confirmed_at',
            ]);
        });
    }
};
