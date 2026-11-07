<?php

namespace App\Actions\Sku;

use App\Models\Definition;
use App\Models\Option;
use App\Models\Product;
use App\Models\Unit;
use App\Models\Variant;

/**
 * Generates the platform identifiers.
 *
 * Product SKU ("HTASIN"): a 10-character opaque platform id, fixed forever.
 *   `H` + 9 characters from an unambiguous alphabet, e.g. `H7K2M9QX4T`.
 *   It deliberately carries no organisation, category or date segment: those
 *   change over a product's life, and an identifier must not.
 *
 * Variant SKU: `{PRODUCT-SKU}-{CODE}...`, e.g. `H7K2M9QX4T-BLU-XL`.
 *
 * Part number: `PN-` + 8 characters. This is only a suggestion the seller can
 *   replace with the manufacturer's part number.
 */
class SkuGeneratorAction
{
    /** Excludes I, O, 0 and 1 so codes stay unambiguous when read aloud. */
    private const ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    private const PRODUCT_LENGTH = 10;

    private const PART_NUMBER_LENGTH = 8;

    private const MAX_ATTEMPTS = 10;

    /**
     * Product SKU. Globally unique (including soft-deleted rows) and stable for
     * the lifetime of the product.
     */
    public function product(): string
    {
        for ($attempt = 0; $attempt < self::MAX_ATTEMPTS; $attempt++) {
            $sku = 'H'.$this->random(self::PRODUCT_LENGTH - 1);

            if (! Product::withTrashed()->where('sku', $sku)->exists()) {
                return $sku;
            }
        }

        throw new \RuntimeException('Unable to allocate a unique product SKU.');
    }

    /** A starting value for `part_number`; the seller may edit it. */
    public function partNumberSuggestion(): string
    {
        return 'PN-'.$this->random(self::PART_NUMBER_LENGTH);
    }

    private function random(int $length): string
    {
        $alphabet = self::ALPHABET;
        $max = strlen($alphabet) - 1;
        $code = '';

        for ($i = 0; $i < $length; $i++) {
            $code .= $alphabet[random_int(0, $max)];
        }

        return $code;
    }

    /**
     * Variant SKU: parent product SKU + one segment per configured attribute.
     * Falls back to a per-product counter when the variant has no configuration.
     */
    public function variant(Product $product, array $configuration, array $configDetails): string
    {
        $suffix = '';

        foreach ($configuration as $code => $value) {
            if (is_null($value) || $value === '') {
                continue;
            }

            if (is_array($value)) {
                $codes = [];
                foreach ($value as $item) {
                    if ($item !== null && $item !== '') {
                        $codes[] = $this->segment((string) $item, 6);
                    }
                }
                if ($codes !== []) {
                    $suffix .= '-'.implode('-', $codes);
                }

                continue;
            }

            $definition = Definition::query()->where('code', (string) $code)->first();

            if (! $definition) {
                $suffix .= '-'.$this->segment((string) $value, 6);

                continue;
            }

            if ($definition->value_type === 'number') {
                $suffix .= '-'.$this->segment((string) $value, 4).$this->unitCode($code, $configDetails);

                continue;
            }

            $option = Option::query()
                ->where('definition_id', $definition->id)
                ->where('name', (string) $value)
                ->first();

            $suffix .= '-'.$this->segment($option?->code ?? (string) $value, 6);
        }

        if ($suffix === '') {
            $count = Variant::withTrashed()->where('product_id', $product->id)->count();
            $suffix = '-'.($count + 1);
        }

        $sku = $product->sku.$suffix;

        // Safety net: never exceed the DB column length (max:100).
        if (strlen($sku) > 100) {
            $sku = $product->sku.'-'.substr(md5($sku), 0, 8);
        }

        return $sku;
    }

    private function unitCode(string $definitionCode, array $configDetails): string
    {
        foreach ($configDetails as $detail) {
            if (($detail['code'] ?? null) === $definitionCode && ! empty($detail['unit_id'])) {
                $unit = Unit::query()->find((int) $detail['unit_id']);
                if ($unit) {
                    return $this->segment($unit->code, 4);
                }
            }
        }

        return '';
    }

    /**
     * Uppercase, strip non-alphanumerics, truncate — keeps SKU segments scanner-safe.
     */
    private function segment(?string $value, int $max): string
    {
        $clean = strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', $value ?? ''));

        return substr($clean, 0, $max);
    }
}
