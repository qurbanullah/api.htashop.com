<?php

namespace App\Enums;

enum ChatRoleEnum: string
{
    case SYSTEM = 'system';
    case USER = 'user';
    case ASSISTANT = 'assistant';
    case TOOL = 'tool';

    /**
     * Get the label for display.
     */
    public function label(): string
    {
        return match ($this) {
            self::SYSTEM => 'System',
            self::USER => 'Visitor',
            self::ASSISTANT => 'Assistant',
            self::TOOL => 'Tool',
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
