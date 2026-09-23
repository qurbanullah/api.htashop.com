<?php

namespace App\Enums;

enum FeedbackPriorityEnum: string
{
    case LOW = 'low';
    case MEDIUM = 'medium';
    case HIGH = 'high';
    case CRITICAL = 'critical';

    /**
     * Get the label for display
     */
    public function label(): string
    {
        return match ($this) {
            self::LOW => 'Low',
            self::MEDIUM => 'Medium',
            self::HIGH => 'High',
            self::CRITICAL => 'Critical',
        };
    }

    /**
     * Get the badge color token for UI display
     */
    public function color(): string
    {
        return match ($this) {
            self::LOW => 'green',
            self::MEDIUM => 'amber',
            self::HIGH => 'orange',
            self::CRITICAL => 'red',
        };
    }

    /**
     * Get all values as array
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
