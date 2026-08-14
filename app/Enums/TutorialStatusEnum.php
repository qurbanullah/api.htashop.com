<?php

namespace App\Enums;

enum TutorialStatusEnum: string
{
    case DRAFT = 'draft';
    case PUBLISHED = 'published';
    case ARCHIVED = 'archived';

    /**
     * Get all available statuses as key-value pairs
     */
    public static function options(): array
    {
        return [
            ['value' => self::DRAFT->value, 'label' => 'Draft'],
            ['value' => self::PUBLISHED->value, 'label' => 'Published'],
            ['value' => self::ARCHIVED->value, 'label' => 'Archived'],
        ];
    }

    /**
     * Get label for the enum value
     */
    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Draft',
            self::PUBLISHED => 'Published',
            self::ARCHIVED => 'Archived',
        };
    }
}
