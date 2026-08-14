<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Dams: Poly morphs AND collections
        Schema::table('dams', function (Blueprint $table) {
            $table->index(['damable_type', 'damable_id'], 'dams_damable_poly_idx');
            $table->index(['damable_type', 'damable_id', 'collection_name'], 'dams_damable_coll_poly_idx');
        });

        // 2. Email Logs
        Schema::table('email_logs', function (Blueprint $table) {
            $table->index(['context_type', 'context_id'], 'email_logs_context_poly_idx');
        });

        // 3. Definition Targets type index
        Schema::table('definition_targets', function (Blueprint $table) {
            $table->index(['target_type'], 'def_targets_type_idx');
        });

        // 4. Products: Tenant + Status
        if (Schema::hasColumn('products', 'tenant_id') && Schema::hasColumn('products', 'status') && Schema::hasColumn('products', 'is_active')) {
            // Already added in add_tenant_id_to_variants_table/organizations_table, but just in case we missed them on licenses
        }

        // 6. Tickets: User + Status composite
        Schema::table('tickets', function (Blueprint $table) {
            $table->index(['user_id', 'status'], 'tickets_user_status_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('dams', function (Blueprint $table) {
            $table->dropIndex('dams_damable_poly_idx');
            $table->dropIndex('dams_damable_coll_poly_idx');
        });

        Schema::table('email_logs', function (Blueprint $table) {
            $table->dropIndex('email_logs_context_poly_idx');
        });

        Schema::table('definition_targets', function (Blueprint $table) {
            $table->dropIndex('def_targets_type_idx');
        });

        Schema::table('licenses', function (Blueprint $table) {
            $table->dropIndex('licenses_user_status_active_idx');
        });

        Schema::table('tickets', function (Blueprint $table) {
            $table->dropIndex('tickets_user_status_idx');
        });
    }
};
