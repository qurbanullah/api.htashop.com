<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('assignments')) {
            Schema::create('assignments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->nullable()->constrained('tenants')->cascadeOnDelete();
                $table->foreignId('organization_id')->nullable()->constrained('organizations')->cascadeOnDelete();
                $table->morphs('assignable');
                $table->string('role')->nullable();
                $table->boolean('is_primary')->default(false);
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->unique(['organization_id', 'assignable_id', 'assignable_type', 'role'], 'assignments_unique_role');
                $table->index(['tenant_id', 'role']);
            });

            return;
        }

        Schema::table('assignments', function (Blueprint $table) {
            if (Schema::hasColumn('assignments', 'assigned_by')) {
                $table->foreignId('assigned_by')->nullable()->change();
            }

            if (Schema::hasColumn('assignments', 'assigned_to')) {
                $table->foreignId('assigned_to')->nullable()->change();
            }

            if (!Schema::hasColumn('assignments', 'tenant_id')) {
                $table->foreignId('tenant_id')->nullable()->after('id')->constrained('tenants')->cascadeOnDelete();
            }

            if (!Schema::hasColumn('assignments', 'organization_id')) {
                $table->foreignId('organization_id')->nullable()->after('tenant_id')->constrained('organizations')->cascadeOnDelete();
            }

            if (!Schema::hasColumn('assignments', 'role')) {
                $table->string('role')->nullable()->after('assignable_id');
            }

            if (!Schema::hasColumn('assignments', 'is_primary')) {
                $table->boolean('is_primary')->default(false)->after('role');
            }

            if (!Schema::hasColumn('assignments', 'metadata')) {
                $table->json('metadata')->nullable()->after('is_primary');
            }
        });

        Schema::table('assignments', function (Blueprint $table) {
            $table->unique(['organization_id', 'assignable_id', 'assignable_type', 'role'], 'assignments_unique_role');
            $table->index(['tenant_id', 'role']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assignments');
    }
};
