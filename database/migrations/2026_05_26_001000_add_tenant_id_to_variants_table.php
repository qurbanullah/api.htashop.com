<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('variants', function (Blueprint $table) {
            $table->foreignId('tenant_id')->nullable()->after('id')->constrained('tenants')->cascadeOnDelete();
        });

        if (DB::getDriverName() === 'sqlite') {
            DB::statement('UPDATE variants SET tenant_id = (SELECT tenant_id FROM products WHERE products.id = variants.product_id)');
        } else {
            DB::statement('UPDATE variants v JOIN products p ON v.product_id = p.id SET v.tenant_id = p.tenant_id');
        }

        Schema::table('variants', function (Blueprint $table) {
            $table->foreignId('tenant_id')->nullable(false)->change();
        });

        Schema::table('variants', function (Blueprint $table) {
            $table->index(['tenant_id', 'status', 'is_active'], 'variants_tenant_status_active_index');
        });
    }

    public function down(): void
    {
        Schema::table('variants', function (Blueprint $table) {
            $table->dropIndex('variants_tenant_status_active_index');
            $table->dropForeign(['tenant_id']);
            $table->dropColumn('tenant_id');
        });
    }
};
