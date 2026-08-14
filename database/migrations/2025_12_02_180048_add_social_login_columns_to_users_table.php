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
            $table->string('provider')->after('is_active')->nullable();
            $table->string('provider_id')->after('provider')->nullable();
            $table->string('provider_token')->nullable()->after('provider_id');
            $table->text('avatar')->after('provider_token')->nullable();
            $table->text('access_token')->after('avatar')->nullable();
            $table->text('refresh_token')->after('access_token')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            //
        });
    }
};
