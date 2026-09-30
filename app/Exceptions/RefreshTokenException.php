<?php

namespace App\Exceptions;

use App\Enums\RefreshTokenFailureEnum;
use RuntimeException;

/**
 * A refresh attempt that cannot succeed. Always surfaces as a 401 to the client,
 * carrying the failure as a machine-readable `code`.
 */
class RefreshTokenException extends RuntimeException
{
    public function __construct(public readonly RefreshTokenFailureEnum $failure)
    {
        parent::__construct($failure->message());
    }
}
