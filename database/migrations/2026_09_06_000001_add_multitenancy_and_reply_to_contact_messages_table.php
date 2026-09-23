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
        Schema::table('contact_messages', function (Blueprint $table) {
            $table->uuid('uuid')->nullable()->after('id')->unique();
            $table->foreignId('tenant_id')->nullable()->after('uuid')->constrained('tenants')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->after('tenant_id')->constrained('users')->nullOnDelete();
            $table->string('order_uuid')->nullable()->after('phone');
            $table->text('admin_response')->nullable()->after('message');
            $table->timestamp('replied_at')->nullable()->after('admin_response');
            $table->foreignId('replied_by')->nullable()->after('replied_at')->constrained('users')->nullOnDelete();
        });

        Schema::table('contact_messages', function (Blueprint $table) {
            $table->index(['tenant_id', 'status', 'created_at'], 'contact_messages_tenant_status_created_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('contact_messages', function (Blueprint $table) {
            $table->dropIndex('contact_messages_tenant_status_created_index');
            $table->dropForeign(['tenant_id', 'user_id', 'replied_by']);
            $table->dropColumn(['tenant_id', 'user_id', 'uuid', 'order_uuid', 'admin_response', 'replied_at', 'replied_by']);
        });
    }
};
