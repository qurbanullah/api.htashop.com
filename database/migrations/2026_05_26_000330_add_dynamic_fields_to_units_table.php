<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('units', function (Blueprint $table) {
            if (!Schema::hasColumn('units', 'uuid')) {
                $table->uuid('uuid')->nullable()->after('id');
            }

            if (!Schema::hasColumn('units', 'tenant_id')) {
                $table->foreignId('tenant_id')->nullable()->after('uuid')->constrained('tenants')->cascadeOnDelete();
            }
        });

        DB::table('units')->whereNull('uuid')->orderBy('id')->get()->each(function ($unit): void {
            $tenantId = DB::table('measurements')->where('id', $unit->measurement_id)->value('tenant_id');

            DB::table('units')->where('id', $unit->id)->update([
                'uuid' => (string) Str::uuid(),
                'tenant_id' => $tenantId,
            ]);
        });

        Schema::table('units', function (Blueprint $table) {
            $table->unique('uuid', 'units_uuid_unique');
            $table->index(['tenant_id', 'is_active'], 'units_tenant_active_index');
        });
    }

    public function down(): void
    {
        Schema::table('units', function (Blueprint $table) {
            $table->dropIndex('units_tenant_active_index');
            $table->dropUnique('units_uuid_unique');

            if (Schema::hasColumn('units', 'tenant_id')) {
                $table->dropConstrainedForeignId('tenant_id');
            }

            if (Schema::hasColumn('units', 'uuid')) {
                $table->dropColumn('uuid');
            }
        });
    }
};
