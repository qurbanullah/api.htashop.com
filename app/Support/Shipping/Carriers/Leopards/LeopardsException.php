<?php

namespace App\Support\Shipping\Carriers\Leopards;

use RuntimeException;

/**
 * A Leopards call failed: credentials rejected, contract changed, or the
 * service is unreachable. Callers decide whether to fall back (rates) or surface
 * an error (booking).
 */
class LeopardsException extends RuntimeException {}
