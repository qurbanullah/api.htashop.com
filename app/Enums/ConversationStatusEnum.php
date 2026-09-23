<?php

namespace App\Enums;

enum ConversationStatusEnum: string
{
    case OPEN = 'open';
    case ESCALATED = 'escalated';
    case CLOSED = 'closed';

    /**
     * Get the label for display.
     */
    public function label(): string
    {
        return match ($this) {
            self::OPEN => 'Open',
            self::ESCALATED => 'Escalated',
            self::CLOSED => 'Closed',
        };
    }

    /**
     * Get the badge color token for UI display.
     */
    public function color(): string
    {
        return match ($this) {
            self::OPEN => 'blue',
            self::ESCALATED => 'amber',
            self::CLOSED => 'gray',
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
