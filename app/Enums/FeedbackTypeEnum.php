<?php

namespace App\Enums;

enum FeedbackTypeEnum: string
{
    case FEEDBACK = 'feedback';
    case FEATURE_REQUEST = 'feature_request';
    case SUGGESTION = 'suggestion';
    case BUG_REPORT = 'bug_report';

    /**
     * Get the label for display
     */
    public function label(): string
    {
        return match ($this) {
            self::FEEDBACK => 'General Feedback',
            self::FEATURE_REQUEST => 'Feature Request',
            self::SUGGESTION => 'Suggestion',
            self::BUG_REPORT => 'Bug Report',
        };
    }

    /**
     * Get the badge color token for UI display
     */
    public function color(): string
    {
        return match ($this) {
            self::FEEDBACK => 'gray',
            self::FEATURE_REQUEST => 'blue',
            self::SUGGESTION => 'purple',
            self::BUG_REPORT => 'red',
        };
    }

    /**
     * Get all values as array
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
