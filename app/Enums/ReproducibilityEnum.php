<?php

namespace App\Enums;

enum ReproducibilityEnum: string
{
    case ALWAYS = 'always';
    case SOMETIMES = 'sometimes';
    case RANDOM = 'random';
    case HAVE_NOT_TRIED = 'have-not-tried';
    case UNABLE_TO_REPRODUCE = 'unable-to-reproduce';
    case NOT_APPLICABLE = 'not-applicable';

    // extra helper to allow for greater customization of displayed values, without disclosing the name/value data directly
    public function label(): string
    {
        return match ($this) {
            self::ALWAYS => 'Always',
            self::SOMETIMES => 'Sometimes',
            self::RANDOM => 'In random order',
            self::HAVE_NOT_TRIED => 'Have not tried',
            self::UNABLE_TO_REPRODUCE => 'Unable to reproduce',
            self::NOT_APPLICABLE => 'Not applicable',
        };
    }
}
