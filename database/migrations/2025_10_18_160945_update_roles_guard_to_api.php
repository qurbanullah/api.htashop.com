<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Update all roles to use 'api' guard instead of 'web'
        DB::table('roles')->update(['guard_name' => 'api']);

        // Update all permissions to use 'api' guard instead of 'web'
        DB::table('permissions')->update(['guard_name' => 'api']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert back to 'web' guard
        DB::table('roles')->update(['guard_name' => 'web']);

        DB::table('permissions')->update(['guard_name' => 'web']);
    }
};
