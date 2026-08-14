<?php

namespace App\Models;


use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;

class Subscription extends Model
{
    use HasFactory;

    /**
     * Business logic extracted to Actions:
     *   subscribeUser()   → App\Actions\Subscription\SubscribeUserAction
     *   unsubscribeUser() → App\Actions\Subscription\UnsubscribeUserAction
     */

    protected $fillable = [
        'subscribeable_id',
        'subscribeable_type',
        'type',
        'is_subscribed',
        'unsubscribe_token',
        'subscribed_at',
        'unsubscribed_at',
    ];

    protected $casts = [
        'is_subscribed' => 'boolean',
        'subscribed_at' => 'datetime',
        'unsubscribed_at' => 'datetime',
    ];

    /**
     * Get the subscribeable model (polymorphic relation)
     */
    public function subscribeable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Generate a unique unsubscribe token
     */
    public static function generateUnsubscribeToken(): string
    {
        do {
            $token = Str::random(64);
        } while (static::where('unsubscribe_token', $token)->exists());

        return $token;
    }

    /**
     * Unsubscribe from this subscription
     */
    public function unsubscribe(): void
    {
        $this->update([
            'is_subscribed' => false,
            'unsubscribed_at' => now(),
        ]);
    }

    /**
     * Subscribe to this subscription
     */
    public function subscribe(): void
    {
        $this->update([
            'is_subscribed' => true,
            'subscribed_at' => now(),
            'unsubscribed_at' => null,
        ]);
    }

    /**
     * Check if subscription is active
     */
    public function isActive(): bool
    {
        return $this->is_subscribed;
    }

    /**
     * Scope to get only active subscriptions
     */
    public function scopeActive($query)
    {
        return $query->where('is_subscribed', true);
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($subscription) {
            if (empty($subscription->unsubscribe_token)) {
                $subscription->unsubscribe_token = static::generateUnsubscribeToken();
            }
            if (is_null($subscription->is_subscribed)) {
                $subscription->is_subscribed = true;
            }
            if (is_null($subscription->subscribed_at) && $subscription->is_subscribed) {
                $subscription->subscribed_at = now();
            }
        });
    }
}
