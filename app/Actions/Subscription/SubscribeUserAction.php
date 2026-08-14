<?php

namespace App\Actions\Subscription;

use App\Models\Subscription;
use Illuminate\Database\Eloquent\Model;

class SubscribeUserAction
{
    public function handle(Model $user, string $type): Subscription
    {
        $subscription = Subscription::firstOrCreate([
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
