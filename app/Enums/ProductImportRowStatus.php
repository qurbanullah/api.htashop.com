<?php

namespace App\Enums;

/** Per-row state inside a product import. */
class ProductImportRowStatus
{
    public const PENDING = 'pending';

    public const VALID = 'valid';

    public const INVALID = 'invalid';

    public const IMPORTED = 'imported';

    public const FAILED = 'failed';

    public const SKIPPED = 'skipped';

    public const ALL = [
        self::PENDING,
        self::VALID,
        self::INVALID,
        self::IMPORTED,
        self::FAILED,
        self::SKIPPED,
    ];
}
