<?php

namespace App\Enums;

enum PriorityEnum: string
{
    case NONE = 'none';
    case LOW = 'low';
    case NORMAL = 'normal';
    case HIGH = 'high';
    case URGENT = 'urgent';
    case IMMIDIATE = 'immidiate';

    // extra helper to allow for greater customization of displayed values, without disclosing the name/value data directly
    public function label(): string
    {
        return match ($this) {
            self::NONE => 'None',
            self::LOW => 'Low',
            self::NORMAL => 'Normal',
            self::HIGH => 'High',
            self::URGENT => 'Urgent',
            self::IMMIDIATE => 'Immidiate',
        };
    }
}
