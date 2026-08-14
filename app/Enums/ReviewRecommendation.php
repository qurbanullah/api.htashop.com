<?php

namespace App\Enums;

enum ReviewRecommendation: string
{
    case ACCEPT = 'accept';
    case MINOR_REVISIONS = 'minor_revisions';
    case MAJOR_REVISIONS = 'major_revisions';
    case REJECT = 'reject';

    /**
     * Get all values as an array
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Get a human-readable label
     */
    public function label(): string
    {
        return match($this) {
            self::ACCEPT => 'Accept',
            self::MINOR_REVISIONS => 'Accept with Minor Revisions',
            self::MAJOR_REVISIONS => 'Major Revisions Required',
            self::REJECT => 'Reject',
        };
    }

    /**
     * Get description
     */
    public function description(): string
    {
        return match($this) {
            self::ACCEPT => 'The manuscript is ready for publication as is',
            self::MINOR_REVISIONS => 'The manuscript needs minor changes before publication',
            self::MAJOR_REVISIONS => 'The manuscript requires substantial revisions',
            self::REJECT => 'The manuscript is not suitable for publication',
        };
    }

    /**
     * Get color for UI
     */
    public function color(): string
    {
        return match($this) {
            self::ACCEPT => 'green',
            self::MINOR_REVISIONS => 'blue',
            self::MAJOR_REVISIONS => 'orange',
            self::REJECT => 'red',
        };
    }

    /**
     * Try to create from string value
     */
    public static function tryFromString(?string $value): ?self
    {
        if ($value === null) {
            return null;
        }

        return self::tryFrom($value);
    }
}
