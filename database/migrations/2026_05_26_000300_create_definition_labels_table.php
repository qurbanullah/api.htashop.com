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
        // Historical filename retained so deployed environments keep a stable migration history.
        // The actual responsibility is to upgrade the shared labels table for tenant-scoped taxonomy.
        Schema::table('labels', function (Blueprint $table) {
            if (!Schema::hasColumn('labels', 'uuid')) {
                $table->uuid('uuid')->nullable()->after('id');
            }

            if (!Schema::hasColumn('labels', 'tenant_id')) {
                $table->foreignId('tenant_id')->nullable()->after('uuid')->constrained('tenants')->cascadeOnDelete();
            }

            if (!Schema::hasColumn('labels', 'metadata')) {
                $table->json('metadata')->nullable()->after('sorting');
            }
        });

        DB::table('labels')->whereNull('uuid')->orderBy('id')->get()->each(function ($label): void {
            DB::table('labels')->where('id', $label->id)->update([
                'uuid' => (string) Str::uuid(),
            ]);
        });

        Schema::table('labels', function (Blueprint $table) {
            $table->unique('uuid', 'labels_uuid_unique');
            $table->dropUnique('labels_slug_unique');
            $table->unique(['tenant_id', 'slug'], 'labels_tenant_slug_unique');
            $table->index(['tenant_id', 'is_active'], 'labels_tenant_active_index');
        });
    }

    public function down(): void
    {
        Schema::table('labels', function (Blueprint $table) {
            $table->dropIndex('labels_tenant_active_index');
            $table->dropUnique('labels_tenant_slug_unique');
            $table->dropUnique('labels_uuid_unique');
            $table->unique('slug', 'labels_slug_unique');

            if (Schema::hasColumn('labels', 'tenant_id')) {
                $table->dropConstrainedForeignId('tenant_id');
            }

            if (Schema::hasColumn('labels', 'metadata')) {
                $table->dropColumn('metadata');
            }

            if (Schema::hasColumn('labels', 'uuid')) {
                $table->dropColumn('uuid');
            }
        });
    }
};