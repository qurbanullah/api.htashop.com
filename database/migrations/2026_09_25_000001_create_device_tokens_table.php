<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Push destinations for the native shells.
     *
     * One row per device: the FCM/APNs registration token a push provider can
     * target, owned by the account that was signed in when it was registered.
     * The token is unique, so signing in as another user on the same device
     * simply reassigns the row instead of leaving a stale owner behind.
     */
    public function up(): void
    {
        Schema::create('device_tokens', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // FCM registration tokens are long; 512 leaves headroom for the
            // APNs/FCM payload without spilling into a TEXT column.
            $table->string('token', 512)->unique();
            $table->string('platform', 20); // android | ios
            // App-scoped device identifier, used to prune rows for a device that
            // has been re-registered with a different push token.
            $table->string('device_id', 191)->nullable();
            $table->string('app_version', 32)->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'platform']);
            $table->index('device_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_tokens');
    }
};
