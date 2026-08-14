<?php

namespace App\Enums;

enum ForumCommentStatusEnum: string
{
    case VISIBLE = 'visible';
    case HIDDEN = 'hidden';

    public function label(): string
    {
        return match ($this) {
            self::VISIBLE => 'Visible',
            self::HIDDEN => 'Hidden',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
