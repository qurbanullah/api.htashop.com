<?php

declare(strict_types=1);

namespace App\Enums\Analytics;

enum EventName: string
{
    case PageView = 'page_view';
    case ProductView = 'product_view';
    case ProductImpression = 'product_impression';
    case Search = 'search';
    case SearchClick = 'search_click';
    case AddToCart = 'add_to_cart';
    case RemoveFromCart = 'remove_from_cart';
    case BeginCheckout = 'begin_checkout';
    case AddPaymentInfo = 'add_payment_info';
    case Purchase = 'purchase';
    case Refund = 'refund';
    case StockOutView = 'stock_out_view';
    case ReviewSubmitted = 'review_submitted';
    case CouponRejected = 'coupon_rejected';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }

    public static function isValid(string $value): bool
    {
        return self::tryFrom($value) !== null;
    }

    public function isServerOnly(): bool
    {
        return in_array($this, [self::Purchase, self::Refund], true);
    }
}
