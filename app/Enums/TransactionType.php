<?php

namespace App\Enums;

class TransactionType
{
    public const CHARGE = 'charge';
    public const CAPTURE = 'capture';
    public const REFUND = 'refund';
    public const PARTIAL_REFUND = 'partial_refund';
    public const REVERSAL = 'reversal';
    public const FEE = 'fee';

    public const ALL = [
        self::CHARGE,
        self::CAPTURE,
        self::REFUND,
        self::PARTIAL_REFUND,
        self::REVERSAL,
        self::FEE,
    ];
}
