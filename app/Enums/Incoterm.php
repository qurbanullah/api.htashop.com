<?php

namespace App\Enums;

/**
 * Incoterms 2020 — the named place is stored separately
 * (`incoterm_location`), e.g. `CIF` + `Karachi Port`.
 */
enum Incoterm: string
{
    case EXW = 'EXW';
    case FCA = 'FCA';
    case FAS = 'FAS';
    case FOB = 'FOB';
    case CFR = 'CFR';
    case CIF = 'CIF';
    case CPT = 'CPT';
    case CIP = 'CIP';
    case DAP = 'DAP';
    case DPU = 'DPU';
    case DDP = 'DDP';

    public function label(): string
    {
        return match ($this) {
            self::EXW => 'EXW — Ex Works',
            self::FCA => 'FCA — Free Carrier',
            self::FAS => 'FAS — Free Alongside Ship',
            self::FOB => 'FOB — Free On Board',
            self::CFR => 'CFR — Cost and Freight',
            self::CIF => 'CIF — Cost, Insurance and Freight',
            self::CPT => 'CPT — Carriage Paid To',
            self::CIP => 'CIP — Carriage and Insurance Paid To',
            self::DAP => 'DAP — Delivered at Place',
            self::DPU => 'DPU — Delivered at Place Unloaded',
            self::DDP => 'DDP — Delivered Duty Paid',
        };
    }

    /** Incoterms that only make sense for sea/river freight. */
    public function isSeaOnly(): bool
    {
        return in_array($this, [self::FAS, self::FOB, self::CFR, self::CIF], true);
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * @return array<int, array{value: string, label: string, sea_only: bool}>
     */
    public static function options(): array
    {
        return array_map(fn (self $case): array => [
            'value' => $case->value,
            'label' => $case->label(),
            'sea_only' => $case->isSeaOnly(),
        ], self::cases());
    }

    public static function labelFor(?string $value): ?string
    {
        return $value === null ? null : self::tryFrom($value)?->label();
    }
}
