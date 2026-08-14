<?php

namespace App\Enums;

enum StatusEnum: string
{
    case OPEN = 'open';
    case CLOSED = 'closed';
    case RESOLVED = 'resolved';
    case ARCHIVED = 'archived';

    // extra helper to allow for greater customization of displayed values, without disclosing the name/value data directly
    public function label(): string
    {
        return match ($this) {
            self::OPEN => 'Open',
            self::CLOSED => 'Closed',
            self::RESOLVED => 'Resolved',
            self::ARCHIVED => 'Archived',
        };
    }
}
