<?php

namespace App\Http\Middleware;

use App\Http\Responses\V1\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Fail-closed gate for order placement.
 *
 * Checkout is disabled until the live payment accounts are approved (see
 * config/checkout.php). Cart, catalog and order history stay available — only
 * the two endpoints that create an order or price a committed checkout are
 * blocked. The API is the source of truth: the storefront also hides the CTA,
 * but a crafted request must never place an order while the switch is off.
 */
class EnsureCheckoutEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        if ((bool) config('checkout.enabled')) {
            return $next($request);
        }

        return ApiResponse::error(
            (string) config('checkout.disabled_message'),
            ['checkout_enabled' => false],
            503
        );
    }
}
