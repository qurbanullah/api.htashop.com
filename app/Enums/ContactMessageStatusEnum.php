<?php

namespace App\Enums;

enum ContactMessageStatusEnum: string
{
    case NEW = 'new';
    case READ = 'read';
    case REPLIED = 'replied';

    /**
     * Get the label for display
     */
    public function label(): string
    {
        return match ($this) {
            self::NEW => 'New',
            self::READ => 'Read',
            self::REPLIED => 'Replied',
        };
    }

    /**
     * Get the badge color token for UI display
     */
    public function color(): string
    {
        return match ($this) {
            self::NEW => 'blue',
            self::READ => 'amber',
            self::REPLIED => 'green',
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
