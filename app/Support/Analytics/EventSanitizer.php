<?php

declare(strict_types=1);

namespace App\Support\Analytics;

/**
 * Whitelist and bound the context dimensions accepted from a public beacon.
 *
 * This is both a privacy control (a client cannot smuggle arbitrary PII into
 * Loki) and a schema control (the raw stream stays queryable and small).
 */
final class EventSanitizer
{
    /**
     * Only these top-level context keys are accepted from the browser. Money
     * data (`order`, `items`, `refund`) is server-authoritative and is emitted
     * by OrderAnalyticsObserver, never by the beacon.
     */
    private const ALLOWED_CONTEXT = [
        'page',
        'product',
        'cart',
        'search',
        'campaign',
        'device',
        'consent',
        'review',
        'coupon',
    ];

    /**
     * Nested fields copied from the client. Everything else is discarded.
     */
    private const ALLOWED_NESTED = [
        'page' => ['path', 'referrer_host', 'url'],
        'product' => ['id', 'variant_id', 'sku', 'category_id', 'name', 'price', 'currency'],
        'cart' => ['items', 'value', 'currency'],
        'search' => ['query', 'results_count', 'zero_result', 'source'],
        'campaign' => ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'gclid', 'fbclid'],
        'device' => ['type', 'browser', 'os'],
        'consent' => ['analytics', 'marketing'],
        'review' => ['rating'],
        'coupon' => ['code', 'reason'],
    ];

    private const MAX_STRING_LENGTH = 500;

    private const MAX_DEPTH = 3;

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public static function context(array $context): array
    {
        $context = array_intersect_key($context, array_flip(self::ALLOWED_CONTEXT));

        foreach (self::ALLOWED_NESTED as $key => $allowed) {
            if (! isset($context[$key]) || ! is_array($context[$key])) {
                unset($context[$key]);

                continue;
            }

            $context[$key] = array_intersect_key($context[$key], array_flip($allowed));
            $context[$key] = self::bound($context[$key], 0);
        }

        return array_filter($context, static fn ($value): bool => $value !== [] && $value !== null);
    }

    private static function bound(mixed $value, int $depth): mixed
    {
        if ($depth > self::MAX_DEPTH) {
            return null;
        }

        if (is_string($value)) {
            return mb_substr($value, 0, self::MAX_STRING_LENGTH);
        }

        if (is_scalar($value) || $value === null) {
            return $value;
        }

        if (! is_array($value)) {
            return null;
        }

        $clean = [];

        foreach ($value as $key => $item) {
            $clean[$key] = self::bound($item, $depth + 1);
        }

        return $clean;
    }
}
