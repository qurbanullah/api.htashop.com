<?php

namespace App\Enums;

enum NewsletterStatusEnum: string
{
    case DRAFT = 'draft';
    case SCHEDULED = 'scheduled';
    case PUBLISHED = 'published';
    case SENT = 'sent';

    /**
     * Get the label for display
     */
    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Draft',
            self::SCHEDULED => 'Scheduled',
            self::PUBLISHED => 'Published',
            self::SENT => 'Sent',
        };
    }

    /**
     * Get the badge color
     */
    public function color(): string
    {
        return match ($this) {
            self::DRAFT => 'gray',
            self::SCHEDULED => 'blue',
            self::PUBLISHED => 'green',
            self::SENT => 'purple',
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
                'color' => $case->color(),
            ],
            self::cases()
        );
    }
}
