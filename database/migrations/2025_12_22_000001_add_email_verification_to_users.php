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
            // Email verification fields
            $table->string('email_verification_token')->nullable()->unique()->after('email_verified_at');
            $table->timestamp('email_verification_sent_at')->nullable()->after('email_verification_token');

            // Onboarding flags
            $table->boolean('profile_completed')->default(false)->after('email_verification_sent_at');
            $table->boolean('onboarding_completed')->default(false)->after('profile_completed');
            $table->timestamp('onboarding_completed_at')->nullable()->after('onboarding_completed');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['email_verification_token']);
            $table->dropColumn([
                'email_verification_token',
                'email_verification_sent_at',
                'profile_completed',
                'onboarding_completed',
                'onboarding_completed_at',
            ]);
        });
    }
};
