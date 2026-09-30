<?php

namespace App\Gateways;

use RuntimeException;

/**
 * An inbound webhook failed verification.
 *
 * Kept separate from "accepted but not actionable": a rejected webhook must
 * return a non-2xx so the gateway surfaces the misconfiguration instead of
 * silently retrying forever, and so a forged request is never mistaken for a
 * delivered one.
 */
class WebhookRejectedException extends RuntimeException {}
