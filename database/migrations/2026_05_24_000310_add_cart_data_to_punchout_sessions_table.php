<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('punchout_sessions', function (Blueprint $table) {
            $table->json('cart_items')->nullable()->after('setup_payload');
            $table->json('cart_payload')->nullable()->after('cart_items');
            $table->timestamp('cart_returned_at')->nullable()->after('completed_at');
        });
    }

    public function down(): void
    {
        Schema::table('punchout_sessions', function (Blueprint $table) {
            $table->dropColumn(['cart_items', 'cart_payload', 'cart_returned_at']);
        });
    }
};
