<?php

namespace App\Actions\Subscription;

use App\Models\NewsletterSubscription;
use Illuminate\Database\Eloquent\Model;

class UnsubscribeUserAction
{
    public function handle(Model $user, string $type): bool
    {
        $subscription = NewsletterSubscription::where([
            'subscribeable_id' => $user->getKey(),
            'subscribeable_type' => get_class($user),
            'type' => $type,
        ])->first();

        if ($subscription) {
            $subscription->unsubscribe();
            return true;
        }

        return false;
    }
}
