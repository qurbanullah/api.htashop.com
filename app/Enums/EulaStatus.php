<?php

namespace App\Enums;

enum EulaStatus: string
{
    case DRAFT = 'draft';
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';

    /**
     * Get all status values
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Get label for display
     */
    public function label(): string
    {
        return match($this) {
            self::DRAFT => 'Draft',
            self::ACTIVE => 'Active',
            self::INACTIVE => 'Inactive',
        };
    }

    /**
     * Get color for display
     */
    public function color(): string
    {
        return match($this) {
            self::DRAFT => 'gray',
            self::ACTIVE => 'green',
            self::INACTIVE => 'red',
        };
    }
}
