<?php

namespace App\Enums;

enum ForumReportStatusEnum: string
{
    case PENDING = 'pending';
    case REVIEWED = 'reviewed';
    case DISMISSED = 'dismissed';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pending',
            self::REVIEWED => 'Reviewed',
            self::DISMISSED => 'Dismissed',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
