<?php

namespace App\Enums;

enum ReviewStatus: string
{
    case DRAFT = 'draft';
    case SUBMITTED = 'submitted';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';

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
            self::DRAFT => 'Draft',
            self::SUBMITTED => 'Submitted',
            self::APPROVED => 'Approved',
            self::REJECTED => 'Rejected',
        };
    }

    /**
     * Get description
     */
    public function description(): string
    {
        return match($this) {
            self::DRAFT => 'Review is being written but not yet submitted',
            self::SUBMITTED => 'Review has been submitted and is pending editor approval',
            self::APPROVED => 'Review has been approved by editor',
            self::REJECTED => 'Review has been rejected by editor',
        };
    }

    /**
     * Get color for UI
     */
    public function color(): string
    {
        return match($this) {
            self::DRAFT => 'gray',
            self::SUBMITTED => 'blue',
            self::APPROVED => 'green',
            self::REJECTED => 'red',
        };
    }

    /**
     * Check if review can be edited
     */
    public function isEditable(): bool
    {
        return $this === self::DRAFT;
    }

    /**
     * Check if review is final
     */
    public function isFinal(): bool
    {
        return in_array($this, [self::APPROVED, self::REJECTED]);
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
