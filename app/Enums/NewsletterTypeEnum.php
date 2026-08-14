<?php

namespace App\Enums;

enum NewsletterTypeEnum: string
{
    case NEWSLETTER = 'newsletter';
    case BLOG = 'blog';
    case NEWS = 'news';
    case EVENT = 'event';
    case ANNOUNCEMENT = 'announcement';

    /**
     * Get the label for display
     */
    public function label(): string
    {
        return match ($this) {
            self::NEWSLETTER => 'Newsletter',
            self::BLOG => 'Blog Post',
            self::NEWS => 'News Article',
            self::EVENT => 'Event',
            self::ANNOUNCEMENT => 'Announcement',
        };
    }

    /**
     * Get the description
     */
    public function description(): string
    {
        return match ($this) {
            self::NEWSLETTER => 'Email newsletter sent to subscribers',
            self::BLOG => 'Blog post published on website',
            self::NEWS => 'News article or press release',
            self::EVENT => 'Event announcement or details',
            self::ANNOUNCEMENT => 'General announcement or update',
        };
    }

    /**
     * Get the icon (for frontend display)
     */
    public function icon(): string
    {
        return match ($this) {
            self::NEWSLETTER => 'mail',
            self::BLOG => 'file-text',
            self::NEWS => 'newspaper',
            self::EVENT => 'calendar',
            self::ANNOUNCEMENT => 'megaphone',
        };
    }

    /**
     * Get all values as array
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Get all as options for select dropdown
     */
    public static function options(): array
    {
        return array_map(
            fn($case) => [
                'value' => $case->value,
                'label' => $case->label(),
                'description' => $case->description(),
                'icon' => $case->icon(),
            ],
            self::cases()
        );
    }
}
