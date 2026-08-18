<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('highlights', function (Blueprint $table) {
            // null = global highlight available to every tenant
            $table->foreignId('tenant_id')->nullable()->after('id')->constrained('tenants')->nullOnDelete();
        });

        Schema::table('highlights', function (Blueprint $table) {
            $table->index(['tenant_id', 'is_active'], 'highlights_tenant_active_index');
        });
    }

    public function down(): void
    {
        Schema::table('highlights', function (Blueprint $table) {
            $table->dropIndex('highlights_tenant_active_index');
            $table->dropForeign(['tenant_id']);
            $table->dropColumn('tenant_id');
        });
    }
};
