<?php

namespace App\Actions\Subscription;

use App\Models\Subscribe;
use Illuminate\Database\Eloquent\Model;

class UnsubscribeUserAction
{
    public function handle(Model $user, string $type): bool
    {
        $subscription = Subscribe::where([
            'subscribable_id' => $user->getKey(),
            'subscribable_type' => get_class($user),
            'type' => $type,
        ])->first();

        if ($subscription) {
            $subscription->unsubscribe();
            return true;
        }

        return false;
    }
}
