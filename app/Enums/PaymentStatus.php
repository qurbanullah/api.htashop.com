<?php

namespace App\Enums;

class PaymentStatus
{
    public const PENDING = 'pending';
    public const AUTHORIZED = 'authorized';
    public const PAID = 'paid';
    public const FAILED = 'failed';
    public const CANCELLED = 'cancelled';
    public const REFUNDED = 'refunded';

    public const ALL = [
        self::PENDING,
        self::AUTHORIZED,
        self::PAID,
        self::FAILED,
        self::CANCELLED,
        self::REFUNDED,
    ];
}
