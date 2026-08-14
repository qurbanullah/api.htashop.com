<?php

namespace App\Enums;

enum CategoryEnum: string
{
    case BILLING = 'billing';
    case GENERAL = 'general';
    case LICENSE = 'license';
    case TECHNICAL = 'technical';
}
