<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Allow guest (email-only) newsletter subscriptions in the unified
     * `subscribes` table: the polymorphic owner becomes optional and an
     * `email` column identifies the subscriber.
     */
    public function up(): void
    {
        Schema::table('subscribes', function (Blueprint $table) {
            $table->string('email')->nullable()->after('type');
            $table->string('subscribable_type')->nullable()->change();
            $table->unsignedBigInteger('subscribable_id')->nullable()->change();
        });

        Schema::table('subscribes', function (Blueprint $table) {
            $table->index(['type', 'email', 'tenant_id'], 'subscribes_type_email_tenant_index');
        });
    }

    public function down(): void
    {
        Schema::table('subscribes', function (Blueprint $table) {
            $table->dropIndex('subscribes_type_email_tenant_index');
            $table->dropColumn('email');
        });
    }
};
