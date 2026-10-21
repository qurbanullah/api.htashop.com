<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->unsignedBigInteger('view_count')->default(0);
            $table->unsignedBigInteger('add_to_cart_count')->default(0);
            $table->unsignedBigInteger('purchase_count')->default(0);

            $table->index('view_count');
            $table->index('purchase_count');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropIndex(['view_count']);
            $table->dropIndex(['purchase_count']);
            $table->dropColumn(['view_count', 'add_to_cart_count', 'purchase_count']);
        });
    }
};
