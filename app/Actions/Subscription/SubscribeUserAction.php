<?php

namespace App\Actions\Subscription;

use App\Models\NewsletterSubscription;
use Illuminate\Database\Eloquent\Model;

class SubscribeUserAction
{
    public function handle(Model $user, string $type): NewsletterSubscription
    {
        $subscription = NewsletterSubscription::firstOrCreate([
            'subscribeable_id' => $user->getKey(),
            'subscribeable_type' => get_class($user),
            'type' => $type,
        ], [
            'is_subscribed' => true,
            'subscribed_at' => now(),
        ]);

        if (! $subscription->is_subscribed) {
            $subscription->subscribe();
        }

        return $subscription;
    }
}
