<?php

namespace App\Services\Coupon;

use RuntimeException;

/**
 * A coupon could not be applied.
 *
 * The message is a **stable token** (`coupon_expired`, `coupon_min_order`, …)
 * rather than prose: the storefront translates it, so the same failure reads
 * correctly in English, Urdu and German.
 */
class CouponException extends RuntimeException
{
    public const NOT_FOUND = 'coupon_not_found';

    public const INACTIVE = 'coupon_inactive';

    public const NOT_STARTED = 'coupon_not_started';

    public const EXPIRED = 'coupon_expired';

    public const MIN_ORDER = 'coupon_min_order';

    public const USAGE_LIMIT = 'coupon_usage_limit_reached';

    public const ALREADY_USED = 'coupon_already_used';

    public function __construct(public readonly string $reason)
    {
        parent::__construct($reason);
    }
}
