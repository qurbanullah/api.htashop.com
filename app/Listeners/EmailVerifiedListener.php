<?php

namespace App\Listeners;

use App\Events\EmailVerifiedFromDevice;
use Illuminate\Auth\Events\Verified;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class EmailVerifiedListener
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(Verified $event): void
    {
        // Log the email verification for audit purposes
        Log::info('Email verified for user', [
            'user_id' => $event->user->id,
            'email' => $event->user->email,
            'verified_at' => now(),
        ]);

        // Store verification status in cache for quick polling access
        Cache::put("email_verified_user_{$event->user->id}", true, now()->addMinutes(10));

        // Store verification timestamp for potential use
        Cache::put("email_verified_at_user_{$event->user->id}", now()->toISOString(), now()->addMinutes(10));

        // Dispatch our custom event for other listeners
        event(new EmailVerifiedFromDevice($event->user));

        // Clear any cached user data that might need updating
        Cache::forget("cached_User_{$event->user->id}");
        Cache::forget("user_verification_status_{$event->user->id}");
    }
}
