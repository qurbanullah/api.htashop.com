<?php

namespace App\Services\Pricing;

use App\Models\DutyRate;
use Illuminate\Support\Collection;

/**
 * Turns a product's HS code (and origin) into an estimated Pakistan import
 * cost.
 *
 * The estimate is informational only: duty and taxes are typically collected
 * from the buyer on delivery, not charged as part of the order total. Rates are
 * looked up by longest-prefix match so a broad heading ("8807") acts as a
 * fallback for any more specific subheading a product carries ("8807.30.00").
 *
 * The breakdown is the real import tax stack: customs duty (with a China–FTA
 * preference when the goods originate in China), additional customs duty,
 * regulatory duty, and sales tax on the duty-paid value.
 */
class DutyRateService
{
    /**
     * The active duty rate that best matches the given HS code, or null.
     */
    public function rateFor(?string $hsCode): ?DutyRate
    {
        $normalized = self::normalize((string) $hsCode);

        if ($normalized === '') {
            return null;
        }

        return $this->rates()
            ->filter(fn (DutyRate $rate) => str_starts_with($normalized, self::normalize($rate->hs_code)))
            ->sortByDesc(fn (DutyRate $rate) => strlen(self::normalize($rate->hs_code)))
            ->first();
    }

    /**
     * Total import duty & taxes owed on an amount, or 0.0 when there is no
     * matching rate.
     */
    public function estimate(float $amount, ?string $hsCode, ?string $originCountry = null): float
    {
        return round($this->breakdown($amount, $hsCode, $originCountry)['total'], 2);
    }

    /**
     * Component breakdown of the import cost.
     *
     * @return array{
     *     customs_duty: float,
     *     additional_customs_duty: float,
     *     regulatory_duty: float,
     *     sales_tax: float,
     *     total: float,
     *     china_fta_applied: bool
     * }
     */
    public function breakdown(float $amount, ?string $hsCode, ?string $originCountry = null): array
    {
        $rate = $this->rateFor($hsCode);

        if ($rate === null) {
            return $this->emptyBreakdown();
        }

        $chinaFta = strtoupper((string) $originCountry) === 'CN'
            && $rate->china_fta_customs_duty !== null;

        $customsDutyRate = $chinaFta
            ? (float) $rate->china_fta_customs_duty
            : (float) $rate->customs_duty;

        $customsDuty = round($amount * ($customsDutyRate / 100), 2);
        $additional = round($amount * ((float) $rate->additional_customs_duty / 100), 2);
        $regulatory = round($amount * ((float) $rate->regulatory_duty / 100), 2);

        // Sales tax is charged on the duty-paid value (goods + CD + ACD + RD).
        $taxable = $amount + $customsDuty + $additional + $regulatory;
        $salesTax = round($taxable * ((float) $rate->sales_tax / 100), 2);

        return [
            'customs_duty' => $customsDuty,
            'additional_customs_duty' => $additional,
            'regulatory_duty' => $regulatory,
            'sales_tax' => $salesTax,
            'total' => round($customsDuty + $additional + $regulatory + $salesTax, 2),
            'china_fta_applied' => $chinaFta,
        ];
    }

    /**
     * @return array<string, float|bool>
     */
    private function emptyBreakdown(): array
    {
        return [
            'customs_duty' => 0.0,
            'additional_customs_duty' => 0.0,
            'regulatory_duty' => 0.0,
            'sales_tax' => 0.0,
            'total' => 0.0,
            'china_fta_applied' => false,
        ];
    }

    /**
     * @return Collection<int, DutyRate>
     */
    private function rates(): Collection
    {
        return DutyRate::query()->where('is_active', true)->get();
    }

    /**
     * Reduce an HS code to its digits so "8501.31.00", "85013100" and
     * "8501-31" all compare as "85013100".
     */
    public static function normalize(string $hsCode): string
    {
        return preg_replace('/\D/', '', $hsCode) ?? '';
    }
}
