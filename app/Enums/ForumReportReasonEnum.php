<?php

namespace App\Enums;

enum ForumReportReasonEnum: string
{
    case SPAM = 'spam';
    case HARASSMENT = 'harassment';
    case INAPPROPRIATE = 'inappropriate';
    case MISINFORMATION = 'misinformation';
    case OFF_TOPIC = 'off_topic';
    case OTHER = 'other';

    public function label(): string
    {
        return match ($this) {
            self::SPAM => 'Spam',
            self::HARASSMENT => 'Harassment',
            self::INAPPROPRIATE => 'Inappropriate Content',
            self::MISINFORMATION => 'Misinformation',
            self::OFF_TOPIC => 'Off Topic',
            self::OTHER => 'Other',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
