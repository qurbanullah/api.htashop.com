<?php

namespace App\Enums;

enum ChatMessageFeedbackEnum: string
{
    case HELPFUL = 'helpful';
    case UNHELPFUL = 'unhelpful';

    /**
     * Get the label for display.
     */
    public function label(): string
    {
        return match ($this) {
            self::HELPFUL => 'Helpful',
            self::UNHELPFUL => 'Not helpful',
        };
    }

    /**
     * Get the badge color token for UI display.
     */
    public function color(): string
    {
        return match ($this) {
            self::HELPFUL => 'green',
            self::UNHELPFUL => 'amber',
        };
    }

    /**
     * Get all values as array.
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
