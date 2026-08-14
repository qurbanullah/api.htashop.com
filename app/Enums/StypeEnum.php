<?php

namespace App\Enums;

enum StypeEnum: string
{
    case BILLING = 'billing';
    case GENERAL = 'general';
    case LICENSE = 'license';
    case TECHNICAL = 'technical';
    case QUESTION = 'question';
    case FEATURE = 'feature-request';
    case SECURITY = 'security-issue';
    case BUG = 'bug';

    // extra helper to allow for greater customization of displayed values, without disclosing the name/value data directly
    public function label(): string
    {
        return match ($this) {
            self::BILLING => 'Billing issue',
            self::GENERAL => 'General issue',
            self::LICENSE => 'License issue',
            self::TECHNICAL => 'Technical issue',
            self::QUESTION => 'Ask a Question',
            self::FEATURE => 'Request a feature',
            self::SECURITY => 'Report a security issue',
            self::BUG => 'Report a bug',
        };
    }
}
