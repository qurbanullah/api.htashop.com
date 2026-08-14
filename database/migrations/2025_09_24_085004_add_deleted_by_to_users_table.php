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
        Schema::table('users', function (Blueprint $table) {
            // Add deleted_by audit fields for soft deletes
            $table->unsignedBigInteger('deleted_by')->nullable()->after('deleted_at');
            $table->string('deleted_by_type')->nullable()->after('deleted_by'); // 'user', 'admin', 'superadmin', 'system'
            $table->string('deleted_by_name')->nullable()->after('deleted_by_type');
            $table->string('deleted_by_email')->nullable()->after('deleted_by_name');
            $table->timestamp('restored_at')->nullable()->after('deleted_by_email');
            $table->unsignedBigInteger('restored_by')->nullable()->after('restored_at');
            $table->string('restored_by_type')->nullable()->after('restored_by');
            $table->string('restored_by_name')->nullable()->after('restored_by_type');
            $table->string('restored_by_email')->nullable()->after('restored_by_name');

            // Add indexes for performance
            $table->index('deleted_by');
            $table->index('deleted_by_type');
            $table->index('restored_by');
            $table->index('restored_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['deleted_by']);
            $table->dropIndex(['deleted_by_type']);
            $table->dropIndex(['restored_by']);
            $table->dropIndex(['restored_at']);

            $table->dropColumn([
                'deleted_by',
                'deleted_by_type',
                'deleted_by_name',
                'deleted_by_email',
                'restored_at',
                'restored_by',
                'restored_by_type',
                'restored_by_name',
                'restored_by_email'
            ]);
        });
    }
};
