<?php

namespace App\Enums;

/**
 * Lifecycle of a product file import.
 *
 *   pending → parsing → validated → importing → completed
 *                                    ↘ completed_with_errors
 *
 * A dry run stops after validating: rows are staged and checked, nothing is
 * written to the catalogue. `validated` is a resting state the operator commits
 * from, so it is deliberately not "finished" and not "running" either.
 */
class ProductImportStatus
{
    public const PENDING = 'pending';

    public const PARSING = 'parsing';

    public const VALIDATING = 'validating';

    /** Parsing finished with a reviewable report; waiting for a commit. */
    public const VALIDATED = 'validated';

    public const IMPORTING = 'importing';

    public const COMPLETED = 'completed';

    public const COMPLETED_WITH_ERRORS = 'completed_with_errors';

    public const FAILED = 'failed';

    public const CANCELLED = 'cancelled';

    public const ALL = [
        self::PENDING,
        self::PARSING,
        self::VALIDATING,
        self::VALIDATED,
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
            self::VALIDATED => 'Validated — ready to import',
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
