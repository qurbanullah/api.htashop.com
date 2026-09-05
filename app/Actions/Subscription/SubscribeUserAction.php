<?php

namespace App\Actions\Subscription;

use App\Models\Subscribe;
use Illuminate\Database\Eloquent\Model;

class SubscribeUserAction
{
    public function handle(Model $user, string $type): Subscribe
    {
        $subscription = Subscribe::firstOrCreate([
            'subscribable_id' => $user->getKey(),
            'subscribable_type' => get_class($user),
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
