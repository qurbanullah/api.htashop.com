<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('values', function (Blueprint $table) {
            $table->index(['valuable_type', 'valuable_id'], 'values_valuable_type_id_index');
            $table->index(['valuable_type', 'valuable_id', 'definition_id'], 'values_valuable_definition_index');
        });

        Schema::table('definitions', function (Blueprint $table) {
            $table->index(['tenant_id', 'group_name', 'section_name'], 'definitions_tenant_group_section_index');
            $table->index(['tenant_id', 'is_active'], 'definitions_tenant_active_index');
        });
    }

    public function down(): void
    {
        Schema::table('values', function (Blueprint $table) {
            $table->dropIndex('values_valuable_type_id_index');
            $table->dropIndex('values_valuable_definition_index');
        });

        Schema::table('definitions', function (Blueprint $table) {
            $table->dropIndex('definitions_tenant_group_section_index');
            $table->dropIndex('definitions_tenant_active_index');
        });
    }
};
