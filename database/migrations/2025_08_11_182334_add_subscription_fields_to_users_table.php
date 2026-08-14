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
            $table->boolean('is_subscribed')->default(true)->after('email_verified_at');
            $table->timestamp('subscribed_at')->nullable()->after('is_subscribed');
            $table->timestamp('unsubscribed_at')->nullable()->after('subscribed_at');
            $table->string('subscription_source')->nullable()->after('unsubscribed_at'); // 'registration', 'newsletter_signup', 'manual', etc.
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'is_subscribed',
                'subscribed_at',
                'unsubscribed_at',
                'subscription_source',
            ]);
        });
    }
};
