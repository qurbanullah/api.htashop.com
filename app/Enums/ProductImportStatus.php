<?php

namespace App\Enums;

/**
 * Lifecycle of a product file import.
 *
 *   pending → parsing → validating → importing → completed
 *                                             ↘ completed_with_errors
 *
 * A dry run stops after validating: rows are staged and checked, nothing is
 * written to the catalogue.
 */
class ProductImportStatus
{
    public const PENDING = 'pending';

    public const PARSING = 'parsing';

    public const VALIDATING = 'validating';

    public const IMPORTING = 'importing';

    public const COMPLETED = 'completed';

    public const COMPLETED_WITH_ERRORS = 'completed_with_errors';

    public const FAILED = 'failed';

    public const CANCELLED = 'cancelled';

    public const ALL = [
        self::PENDING,
        self::PARSING,
        self::VALIDATING,
        self::IMPORTING,
        self::COMPLETED,
        self::COMPLETED_WITH_ERRORS,
        self::FAILED,
        self::CANCELLED,
    ];

    public static function label(string $status): string
    {
        return match ($status) {
            self::PENDING => 'Pending',
            self::PARSING => 'Parsing file',
            self::VALIDATING => 'Validating rows',
            self::IMPORTING => 'Importing',
            self::COMPLETED => 'Completed',
            self::COMPLETED_WITH_ERRORS => 'Completed with errors',
            self::FAILED => 'Failed',
            self::CANCELLED => 'Cancelled',
            default => ucfirst($status),
        };
    }

    public static function isFinished(string $status): bool
    {
        return in_array($status, [
            self::COMPLETED,
            self::COMPLETED_WITH_ERRORS,
            self::FAILED,
            self::CANCELLED,
        ], true);
    }
}
