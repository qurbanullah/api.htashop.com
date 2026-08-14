<?php

namespace App\Enums;

enum SeverityEnum: string
{
    case FEATURE = 'feature';
    case TRIVIAL = 'trivial';
    case TEXT = 'text';
    case TWEAK = 'tweak';
    case MINOR = 'minor';
    case MAJOR = 'major';
    case CRASH = 'crash';
    case BLOCK = 'block';

    // extra helper to allow for greater customization of displayed values, without disclosing the name/value data directly
    public function label(): string
    {
        return match ($this) {
            self::FEATURE => 'Feature',
            self::TRIVIAL => 'Trivial',
            self::TEXT => 'Text',
            self::TWEAK => 'Tweak',
            self::MINOR => 'Minor',
            self::MAJOR => 'Major',
            self::CRASH => 'Crash',
            self::BLOCK => 'Block',
        };
    }
}
