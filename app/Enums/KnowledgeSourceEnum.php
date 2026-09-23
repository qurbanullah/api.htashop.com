<?php

namespace App\Enums;

enum KnowledgeSourceEnum: string
{
    case MANUAL = 'manual';
    case FAQ = 'faq';
    case POST = 'post';
    case TUTORIAL = 'tutorial';
    case PRODUCT = 'product';
    case POLICY = 'policy';

    /**
     * Get the label for display.
     */
    public function label(): string
    {
        return match ($this) {
            self::MANUAL => 'Manual',
            self::FAQ => 'FAQ',
            self::POST => 'Post',
            self::TUTORIAL => 'Tutorial',
            self::PRODUCT => 'Product',
            self::POLICY => 'Policy',
        };
    }

    /**
     * Get all values as array.
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
