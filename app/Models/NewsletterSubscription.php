<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;

/**
 * Newsletter/announcement subscription (legacy newsletter feature).
 * Distinct from the product `subscriptions` table — see Subscription.
 */
class NewsletterSubscription extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'newsletter_subscriptions';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'type',
        'subscribeable_type',
        'subscribeable_id',
        'is_subscribed',
        'subscribed_at',
        'unsubscribed_at',
        'unsubscribe_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_subscribed' => 'boolean',
        'subscribed_at' => 'datetime',
        'unsubscribed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function (NewsletterSubscription $subscription) {
            if (empty($subscription->unsubscribe_token)) {
                $subscription->unsubscribe_token = (string) Str::uuid();
            }
        });
    }

    /**
     * Polymorphic relationship: the entity that subscribed (User, etc.)
     */
    public function subscribeable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Mark the subscription as active.
     */
    public function subscribe(): bool
    {
        $this->is_subscribed = true;
        $this->subscribed_at = now();
        $this->unsubscribed_at = null;

        return $this->save();
    }

    /**
     * Mark the subscription as inactive.
     */
    public function unsubscribe(): bool
    {
        $this->is_subscribed = false;
        $this->unsubscribed_at = now();

        return $this->save();
    }

    /**
     * Scope: active subscriptions of a given type.
     */
    public function scopeActive($query, string $type)
    {
        return $query->where('type', $type)
            ->where('is_subscribed', true);
    }
}
