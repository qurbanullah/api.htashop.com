<?php

namespace App\Enums;

enum ForumPostStatusEnum: string
{
    case PUBLISHED = 'published';
    case PENDING = 'pending';
    case HIDDEN = 'hidden';
    case ARCHIVED = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::PUBLISHED => 'Published',
            self::PENDING => 'Pending Review',
            self::HIDDEN => 'Hidden',
            self::ARCHIVED => 'Archived',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
