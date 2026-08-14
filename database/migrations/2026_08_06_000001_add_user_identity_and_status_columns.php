<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('first_name')->nullable()->after('name');
            $table->string('middle_name')->nullable()->after('first_name');
            $table->string('last_name')->nullable()->after('middle_name');
            $table->string('status')->default('active')->after('last_name');
            $table->timestamp('last_login_at')->nullable()->after('remember_token');
            $table->string('last_login_ip', 45)->nullable()->after('last_login_at');
            $table->index('status');
        });

        // Migrate is_active → status (run after schema change)
        if (Schema::hasColumn('users', 'is_active')) {
            DB::statement("UPDATE users SET status = 'suspended' WHERE is_active = 0");
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('is_active');
            });
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropColumn([
                'first_name',
                'middle_name',
                'last_name',
                'status',
                'last_login_at',
                'last_login_ip',
            ]);

            // Restore is_active
            $table->boolean('is_active')->default(true)->after('last_name');
        });
    }
};
