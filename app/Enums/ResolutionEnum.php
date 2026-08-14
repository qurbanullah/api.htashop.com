<?php

namespace App\Enums;

/**
 * Avatar Resolution Enum
 *
 * Defines available avatar resolutions.
 * New resolutions can be added without database schema changes.
 * Storage: avatar_variants.resolution (string column)
 */
enum ResolutionEnum: string
{
    /**
     * Thumbnail resolution: 50x50 pixels
     * Use: UI avatars in lists, sidebars, comments
     */
    case THUMB = 'thumb';

    /**
     * Small resolution: 150x150 pixels
     * Use: Profile headers, notifications
     */
    case SMALL = 'small';

    /**
     * Medium resolution: 300x300 pixels
     * Use: Profile pages, modals
     */
    case MEDIUM = 'medium';

    /**
     * Large resolution: 500x500 pixels
     * Use: Print, high-res displays
     */
    case LARGE = 'large';

    /**
     * Extra-large resolution: 1000x1000 pixels
     * Use: Marketing, presentation materials
     */
    case XL = 'xl';

    /**
     * Get the pixel dimension for this resolution
     * Useful for generating thumbnails and responsive images
     */
    public function dimension(): int
    {
        return match ($this) {
            self::THUMB => 50,
            self::SMALL => 150,
            self::MEDIUM => 300,
            self::LARGE => 500,
            self::XL => 1000,
        };
    }

    /**
     * Get human-readable label
     */
    public function label(): string
    {
        return match ($this) {
            self::THUMB => 'Thumbnail (50x50)',
            self::SMALL => 'Small (150x150)',
            self::MEDIUM => 'Medium (300x300)',
            self::LARGE => 'Large (500x500)',
            self::XL => 'Extra Large (1000x1000)',
        };
    }

    /**
     * Check if resolution is default (medium)
     */
    public function isDefault(): bool
    {
        return $this === self::MEDIUM;
    }

    /**
     * Get all available resolutions
     */
    public static function all(): array
    {
        return self::cases();
    }

    /**
     * Get all resolution values
     */
    public static function values(): array
    {
        return array_map(fn (self $case) => $case->value, self::cases());
    }
}
