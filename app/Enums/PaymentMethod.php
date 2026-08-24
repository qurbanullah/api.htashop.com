<?php

namespace App\Enums;

class PaymentMethod
{
    public const COD = 'cod';
    public const JAZZCASH = 'jazzcash';
    public const EASYPAISA = 'easypaisa';
    public const UPAISA = 'upaisa';
    public const SAFEPAY = 'safepay';

    public const ALL = [
        self::COD,
        self::JAZZCASH,
        self::EASYPAISA,
        self::UPAISA,
        self::SAFEPAY,
    ];

    public static function label(string $method): string
    {
        return match ($method) {
            self::COD => 'Cash on Delivery',
            self::JAZZCASH => 'JazzCash',
            self::EASYPAISA => 'EasyPaisa',
            self::UPAISA => 'UPaisa',
            self::SAFEPAY => 'Safepay',
            default => ucfirst($method),
        };
    }
}
