<?php

namespace App\Enums;

enum ReviewerStatus: string
{
    case PENDING = 'pending';
    case IN_PROGRESS = 'in_progress';
    case COMPLETED = 'completed';
    case DECLINED = 'declined';

    /**
     * Get all values as an array
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Get a human-readable label
     */
    public function label(): string
    {
        return match($this) {
            self::PENDING => 'Pending',
            self::IN_PROGRESS => 'In Progress',
            self::COMPLETED => 'Completed',
            self::DECLINED => 'Declined',
        };
    }

    /**
     * Get description
     */
    public function description(): string
    {
        return match($this) {
            self::PENDING => 'Invitation sent, awaiting response',
            self::IN_PROGRESS => 'Reviewer has accepted and is working on the review',
            self::COMPLETED => 'Review has been submitted',
            self::DECLINED => 'Reviewer declined the invitation',
        };
    }

    /**
     * Get color for UI
     */
    public function color(): string
    {
        return match($this) {
            self::PENDING => 'yellow',
            self::IN_PROGRESS => 'blue',
            self::COMPLETED => 'green',
            self::DECLINED => 'gray',
        };
    }

    /**
     * Check if reviewer can accept
     */
    public function canAccept(): bool
    {
        return $this === self::PENDING;
    }

    /**
     * Check if reviewer can decline
     */
    public function canDecline(): bool
    {
        return $this === self::PENDING;
    }

    /**
     * Check if reviewer can submit review
     */
    public function canSubmit(): bool
    {
        return $this === self::IN_PROGRESS;
    }

    /**
     * Check if reviewer can edit review
     */
    public function canEdit(): bool
    {
        return in_array($this, [self::PENDING, self::IN_PROGRESS]);
    }

    /**
     * Try to create from string value
     */
    public static function tryFromString(?string $value): ?self
    {
        if ($value === null) {
            return null;
        }

        return self::tryFrom($value);
    }
}
