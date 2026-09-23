<?php

namespace App\Actions\Subscription;

use App\Models\Subscribe;
use Illuminate\Support\Facades\Log;

/**
 * Subscribe (or re-subscribe) an email address to the store newsletter.
 *
 * Guest rows live in the unified `subscribes` table with `type = newsletter`,
 * the resolved `tenant_id`, and the subscriber's `email`. The polymorphic
 * owner is intentionally left null for email-only subscribers.
 */
class SubscribeNewsletterAction
{
    public const TYPE = 'newsletter';

    /**
     * @return array{0: Subscribe, 1: 'subscribed'|'already_subscribed'|'resubscribed'}
     */
    public function handle(string $email, ?int $tenantId = null, array $metadata = []): array
    {
        $email = strtolower(trim($email));

        $subscription = Subscribe::query()
            ->where('type', self::TYPE)
            ->where('email', $email)
            ->when($tenantId, fn ($query) => $query->where('tenant_id', $tenantId), fn ($query) => $query->whereNull('tenant_id'))
            ->first();

        if ($subscription) {
            if ($subscription->is_subscribed) {
                return [$subscription, 'already_subscribed'];
            }

            $subscription->subscribe();
            Log::info('Newsletter re-subscribed', [
                'subscribe_id' => $subscription->id,
                'email' => $email,
                'tenant_id' => $tenantId,
            ]);

            return [$subscription, 'resubscribed'];
        }

        $subscription = Subscribe::create([
            'type' => self::TYPE,
            'tenant_id' => $tenantId,
            'email' => $email,
            'is_subscribed' => true,
            'subscribed_at' => now(),
            'metadata' => $metadata,
        ]);

        Log::info('Newsletter subscription created', [
            'subscribe_id' => $subscription->id,
            'email' => $email,
            'tenant_id' => $tenantId,
        ]);

        return [$subscription, 'subscribed'];
    }
}
