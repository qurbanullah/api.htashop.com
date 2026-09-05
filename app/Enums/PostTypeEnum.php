<?php

namespace App\Enums;

enum PostTypeEnum: string
{
    case POST = 'post';
    case BLOG = 'blog';
    case NEWS = 'news';
    case EVENT = 'event';
    case SHOWCASE = 'showcase';
    case ANNOUNCEMENT = 'announcement';
    case PROMOTION = 'promotion';
    case UPDATE = 'update';
    case TUTORIAL = 'tutorial';
    case PRESS_RELEASE = 'press_release';

    /**
     * Get the label for display
     */
    public function label(): string
    {
        return match ($this) {
            self::POST => 'Post',
            self::BLOG => 'Blog Post',
            self::NEWS => 'News Article',
            self::EVENT => 'Event',
            self::SHOWCASE => 'Showcase',
            self::ANNOUNCEMENT => 'Announcement',
            self::PROMOTION => 'Promotion',
            self::UPDATE => 'Product Update',
            self::TUTORIAL => 'Tutorial',
            self::PRESS_RELEASE => 'Press Release',
        };
    }

    /**
     * Get the description
     */
    public function description(): string
    {
        return match ($this) {
            self::POST => 'Email post sent to subscribers',
            self::BLOG => 'Blog post published on the website',
            self::NEWS => 'News article or press release',
            self::EVENT => 'Event announcement or details',
            self::SHOWCASE => 'Product showcase or spotlight',
            self::ANNOUNCEMENT => 'General announcement or update',
            self::PROMOTION => 'Special offer, discount, or campaign',
            self::UPDATE => 'Product or platform update',
            self::TUTORIAL => 'How-to guide or tutorial',
            self::PRESS_RELEASE => 'Official press release',
        };
    }

    /**
     * Get the icon (for frontend display — lucide icon name)
     */
    public function icon(): string
    {
        return match ($this) {
            self::POST => 'mail',
            self::BLOG => 'file-text',
            self::NEWS => 'newspaper',
            self::EVENT => 'calendar',
            self::SHOWCASE => 'sparkles',
            self::ANNOUNCEMENT => 'megaphone',
            self::PROMOTION => 'badge-percent',
            self::UPDATE => 'refresh-cw',
            self::TUTORIAL => 'book-open',
            self::PRESS_RELEASE => 'mic',
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
