<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('manufacturers', function (Blueprint $table) {
            $table->string('origin')->default('admin')->after('type'); // admin | vendor
            $table->boolean('is_approved')->default(true)->after('origin');
            $table->foreignId('tenant_id')->nullable()->after('is_approved')->constrained('tenants')->nullOnDelete();
            $table->foreignId('organization_id')->nullable()->after('tenant_id')->constrained('organizations')->nullOnDelete();
            $table->timestamp('approved_at')->nullable()->after('organization_id');
            $table->timestamp('rejected_at')->nullable()->after('approved_at');
            $table->string('rejection_reason')->nullable()->after('rejected_at');

            $table->index(['is_approved', 'origin']);
        });

        Schema::table('brands', function (Blueprint $table) {
            $table->string('origin')->default('admin')->after('manufacturer_id'); // admin | vendor
            $table->boolean('is_approved')->default(true)->after('origin');
            $table->foreignId('tenant_id')->nullable()->after('is_approved')->constrained('tenants')->nullOnDelete();
            $table->foreignId('organization_id')->nullable()->after('tenant_id')->constrained('organizations')->nullOnDelete();
            $table->timestamp('approved_at')->nullable()->after('organization_id');
            $table->timestamp('rejected_at')->nullable()->after('approved_at');
            $table->string('rejection_reason')->nullable()->after('rejected_at');

            $table->index(['is_approved', 'origin']);
        });
    }

    public function down(): void
    {
        Schema::table('brands', function (Blueprint $table) {
            $table->dropForeign(['tenant_id']);
            $table->dropForeign(['organization_id']);
            $table->dropIndex(['is_approved', 'origin']);
            $table->dropColumn(['origin', 'is_approved', 'tenant_id', 'organization_id', 'approved_at', 'rejected_at', 'rejection_reason']);
        });

        Schema::table('manufacturers', function (Blueprint $table) {
            $table->dropForeign(['tenant_id']);
            $table->dropForeign(['organization_id']);
            $table->dropIndex(['is_approved', 'origin']);
            $table->dropColumn(['origin', 'is_approved', 'tenant_id', 'organization_id', 'approved_at', 'rejected_at', 'rejection_reason']);
        });
    }
};
