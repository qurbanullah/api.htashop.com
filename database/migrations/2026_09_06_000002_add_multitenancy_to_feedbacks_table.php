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
        Schema::table('feedbacks', function (Blueprint $table) {
            $table->foreignId('tenant_id')->nullable()->after('uuid')->constrained('tenants')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->after('tenant_id')->constrained('users')->nullOnDelete();
            $table->string('page_url')->nullable()->after('source');
        });

        Schema::table('feedbacks', function (Blueprint $table) {
            $table->index(['tenant_id', 'status', 'created_at'], 'feedbacks_tenant_status_created_index');
        });

        // The software-specific columns were leftovers from the previous
        // product; context like this now lives in additional_info JSON.
        Schema::table('feedbacks', function (Blueprint $table) {
            $table->dropColumn(['software_name', 'software_version', 'operating_system']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('feedbacks', function (Blueprint $table) {
            $table->string('software_name')->nullable();
            $table->string('software_version')->nullable();
            $table->string('operating_system')->nullable();
        });

        Schema::table('feedbacks', function (Blueprint $table) {
            $table->dropIndex('feedbacks_tenant_status_created_index');
            $table->dropForeign(['tenant_id', 'user_id']);
            $table->dropColumn(['tenant_id', 'user_id', 'page_url']);
        });
    }
};
