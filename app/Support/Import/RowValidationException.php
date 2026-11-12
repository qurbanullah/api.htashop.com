<?php

namespace App\Support\Import;

use RuntimeException;

/**
 * A single import row that cannot be applied. `errors` is a field → message
 * map, mirroring Laravel validation output, so the error report is familiar.
 */
class RowValidationException extends RuntimeException
{
    /**
     * @param  array<string, string>  $errors
     * @param  array<string, mixed>|null  $payload
     */
    public function __construct(
        public readonly array $errors,
        public readonly ?array $payload = null,
    ) {
        parent::__construct((string) (reset($errors) ?: 'Invalid row.'));
    }
}
