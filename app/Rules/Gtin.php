<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A GS1 GTIN.
 *
 * Supported lengths and their usual encodings:
 *   8  → GTIN-8  (EAN-8)
 *   12 → GTIN-12 (UPC-A)
 *   13 → GTIN-13 (EAN-13 / JAN)
 *   14 → GTIN-14 (ITF-14 / GS1-128, outer packaging)
 *
 * The final digit is a modulo-10 check digit, so a mistyped number is caught
 * before it reaches a barcode printer or a marketplace feed.
 */
class Gtin implements ValidationRule
{
    private const TYPES = [
        8 => 'GTIN-8',
        12 => 'GTIN-12',
        13 => 'GTIN-13',
        14 => 'GTIN-14',
    ];

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        if (! is_string($value) || ! preg_match('/^\d+$/', $value)) {
            $fail('The :attribute must contain digits only.');

            return;
        }

        if (! array_key_exists(strlen($value), self::TYPES)) {
            $fail('The :attribute must be a GTIN-8, GTIN-12, GTIN-13 or GTIN-14 (8, 12, 13 or 14 digits).');

            return;
        }

        if (! self::hasValidCheckDigit($value)) {
            $fail('The :attribute check digit is invalid — recheck the number.');
        }
    }

    /** Human label for a GTIN, e.g. `GTIN-13`. */
    public static function type(string $gtin): ?string
    {
        return self::TYPES[strlen($gtin)] ?? null;
    }

    /** True for a blank value or a well-formed, correctly-checked GTIN. */
    public static function isValid(?string $value): bool
    {
        if ($value === null || $value === '') {
            return true;
        }

        return preg_match('/^\d+$/', $value) === 1
            && array_key_exists(strlen($value), self::TYPES)
            && self::hasValidCheckDigit($value);
    }

    public static function hasValidCheckDigit(string $gtin): bool
    {
        $digits = array_map('intval', str_split($gtin));
        $check = array_pop($digits);
        $sum = 0;

        // From the rightmost payload digit, weights alternate 3, 1, 3, 1…
        foreach (array_reverse($digits) as $index => $digit) {
            $sum += $digit * ($index % 2 === 0 ? 3 : 1);
        }

        return (10 - ($sum % 10)) % 10 === $check;
    }
}
