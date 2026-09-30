<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Discount codes become scoped.
     *
     * A coupon belongs to at most one scope, resolved most-specific-first:
     *
     *   organization_id set  ->  the code applies only to that organization
     *   tenant_id set        ->  the code applies to the whole tenant
     *   both null            ->  platform-wide (what HTAShop ships today)
     *
     * Uniqueness follows the scope, so a merchant's `EID10` and another
     * merchant's `EID10` are different coupons. A plain composite unique index
     * cannot express that: MySQL and MariaDB treat NULLs as distinct, so
     * `(code, tenant_id, organization_id)` would happily store two global
     * `SAVE10` rows. `scope_code` is a stored generated column that collapses
     * the two nullable scope columns to `0`, which makes a single unique index
     * correct for every combination — including "global".
     *
     * The foreign keys cascade rather than null out: deleting a tenant must not
     * *promote* its codes to platform-wide, and deleting an organization must
     * not drop its codes down to tenant-wide. A scoped coupon is owned by its
     * scope and dies with it. (MariaDB also refuses a generated column over an
     * FK column with `ON DELETE SET NULL`, so the correct semantics and the
     * schema feature happen to agree.)
     */
    public function up(): void
    {
        Schema::table('coupons', function (Blueprint $table) {
            $table->foreignId('tenant_id')->nullable()->after('uuid')
                ->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('organization_id')->nullable()->after('tenant_id')
                ->constrained('organizations')->cascadeOnDelete();
        });

        Schema::table('coupons', function (Blueprint $table) {
            $table->dropUnique('coupons_code_unique');

            $table->string('scope_code', 320)
                ->storedAs("concat(code, ':', coalesce(tenant_id, 0), ':', coalesce(organization_id, 0))");

            $table->unique('scope_code');
        });
    }

    public function down(): void
    {
        Schema::table('coupons', function (Blueprint $table) {
            $table->dropUnique(['scope_code']);
            $table->dropColumn('scope_code');
            $table->unique('code');
        });

        Schema::table('coupons', function (Blueprint $table) {
            $table->dropConstrainedForeignId('organization_id');
            $table->dropConstrainedForeignId('tenant_id');
        });
    }
};
