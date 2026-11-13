<?php

namespace App\Support\Quotes;

use App\Models\QuoteRequest;
use App\Models\QuoteResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;

/**
 * Builds the signed storefront link embedded in buyer emails.
 *
 * The signature is generated for the API portal route, then its query string is
 * transplanted onto the storefront URL. The storefront forwards those params
 * back to the same API route, so Laravel's `signed` middleware validates it
 * without ever putting the API origin in front of the buyer.
 */
class QuotePortalLink
{
    public static function for(QuoteRequest $quote, QuoteResponse $response): string
    {
        $signed = URL::temporarySignedRoute('quotes.portal', self::expiryFor($response), [
            'uuid' => $quote->uuid,
        ]);

        $query = parse_url($signed, PHP_URL_QUERY);
        $base = rtrim((string) config('app.frontend_url'), '/');

        return $base.'/quote/'.$quote->uuid.($query !== null ? '?'.$query : '');
    }

    private static function expiryFor(QuoteResponse $response): Carbon
    {
        $expires = $response->expires_at?->copy()
            ?? now()->addDays(max(1, (int) ($response->validity_days ?: 30)));

        if ($expires->isPast()) {
            $expires = now();
        }

        return $expires->addDays((int) config('quotes.portal.link_grace_days', 14));
    }
}
