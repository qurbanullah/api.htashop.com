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
        Schema::table('deleted_users', function (Blueprint $table) {
            $table->unsignedBigInteger('deleted_by')->nullable()->after('original_user_id');
            $table->string('deleted_by_type')->default('system')->after('deleted_by'); // 'user', 'admin', 'superadmin', 'system'
            $table->string('deleted_by_name')->nullable()->after('deleted_by_type'); // Name of who deleted for audit
            $table->string('deleted_by_email')->nullable()->after('deleted_by_name'); // Email of who deleted for audit

            $table->index(['deleted_by']);
            $table->index(['deleted_by_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('deleted_users', function (Blueprint $table) {
            $table->dropIndex(['deleted_by']);
            $table->dropIndex(['deleted_by_type']);
            $table->dropColumn(['deleted_by', 'deleted_by_type', 'deleted_by_name', 'deleted_by_email']);
        });
    }
};
