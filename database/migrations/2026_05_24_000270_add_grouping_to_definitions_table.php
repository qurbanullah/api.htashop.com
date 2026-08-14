<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('definitions', function (Blueprint $table) {
            if (!Schema::hasColumn('definitions', 'group_name')) {
                $table->string('group_name')->nullable()->after('value_type');
            }

            if (!Schema::hasColumn('definitions', 'section_name')) {
                $table->string('section_name')->nullable()->after('group_name');
            }

            $table->index(['tenant_id', 'kind', 'group_name'], 'definitions_tenant_kind_group_index');
            $table->index(['tenant_id', 'section_name'], 'definitions_tenant_section_index');
        });
    }

    public function down(): void
    {
        Schema::table('definitions', function (Blueprint $table) {
            $table->dropIndex('definitions_tenant_kind_group_index');
            $table->dropIndex('definitions_tenant_section_index');

            if (Schema::hasColumn('definitions', 'section_name')) {
                $table->dropColumn('section_name');
            }

            if (Schema::hasColumn('definitions', 'group_name')) {
                $table->dropColumn('group_name');
            }
        });
    }
};
