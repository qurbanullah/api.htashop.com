<?php

namespace App\Enums;

enum LicenseStatusEnum: string
{
    case PENDING = 'pending';
    case INPROGRESS = 'in-progress';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';

    // extra helper to allow for greater customization of displayed values, without disclosing the name/value data directly
    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pending',
            self::INPROGRESS => 'In progress',
            self::APPROVED => 'Approved',
            self::REJECTED => 'Rejected',
        };
    }
}
