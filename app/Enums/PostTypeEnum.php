<?php

namespace App\Enums;

enum PostTypeEnum: string
{
    case BLOG = 'blog';
    case NEWS = 'news';
    case EVENT = 'event';
    case ANNOUNCEMENT = 'announcement';
    case NEWSLETTER = 'newsletter';
    case PROMOTION = 'promotion';
    case UPDATE = 'update';
    case SHOWCASE = 'showcase';
    case TUTORIAL = 'tutorial';
    case PRESS_RELEASE = 'press_release';

    /**
     * Get the label for display
     */
    public function label(): string
    {
        return match ($this) {
            self::BLOG => 'Blog Post',
            self::NEWS => 'News Article',
            self::EVENT => 'Event',
            self::ANNOUNCEMENT => 'Announcement',
            self::NEWSLETTER => 'Newsletter',
            self::PROMOTION => 'Promotion',
            self::UPDATE => 'Product Update',
            self::SHOWCASE => 'Showcase',
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
            self::BLOG => 'Blog post published on the website',
            self::NEWS => 'News article or press release',
            self::EVENT => 'Event announcement or details',
            self::ANNOUNCEMENT => 'General announcement or update',
            self::NEWSLETTER => 'Email post sent to subscribers',
            self::PROMOTION => 'Special offer, discount, or campaign',
            self::UPDATE => 'Product or platform update',
            self::SHOWCASE => 'Product showcase or spotlight',
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
            self::BLOG => 'file-text',
            self::NEWS => 'newspaper',
            self::EVENT => 'calendar',
            self::ANNOUNCEMENT => 'megaphone',
            self::NEWSLETTER => 'mail',
            self::PROMOTION => 'badge-percent',
            self::UPDATE => 'refresh-cw',
            self::SHOWCASE => 'sparkles',
            self::TUTORIAL => 'book-open',
            self::PRESS_RELEASE => 'mic',
        };
    }

    /**
     * Public storefront path segment for this post type.
     *
     * Every type has its own dedicated section, so each is separately
     * indexable and can grow its own landing page. The match is exhaustive on
     * purpose — adding a new case without deciding its public section fails
     * loudly (UnhandledMatchError) rather than silently losing a page.
     *
     * Tutorial posts are served under /guides because /tutorials already
     * belongs to the standalone Tutorial system (PublicTutorialController).
     */
    public function webSection(): string
    {
        return match ($this) {
            self::BLOG => '/blogs',
            self::NEWS => '/news',
            self::EVENT => '/events',
            self::ANNOUNCEMENT => '/announcements',
            self::PRESS_RELEASE => '/press-releases',
            self::PROMOTION => '/promotions',
            self::UPDATE => '/updates',
            self::SHOWCASE => '/showcases',
            self::TUTORIAL => '/guides',
            self::NEWSLETTER => '/newsletters',
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
