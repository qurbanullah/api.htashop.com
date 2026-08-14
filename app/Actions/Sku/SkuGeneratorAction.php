<?php

namespace App\Actions\Sku;

use App\Models\Category;
use App\Models\Definition;
use App\Models\Option;
use App\Models\Organization;
use App\Models\Product;
use App\Models\Unit;
use App\Models\Variant;

/**
 * Generates the system (standard) SKUs.
 *
 * Product:  {ORG}-{CAT}-{YYMM}-{SEQ}   e.g. HTA-EL-2608-000001
 * Variant:  {PRODUCT-SKU}-{CODE}...    e.g. HTA-EL-2608-000001-BLU-XL
 */
class SkuGeneratorAction
{
    /**
     * Product SKU. Sequence resets yearly, scoped per (organization, category, year).
     */
    public function product(Organization $organization, ?Category $category, int $tenantId): string
    {
        $orgCode = $this->segment($organization->code ?? $organization->slug, 4) ?: 'ORG';
        $catCode = $this->segment($category?->code ?? $category?->slug ?? '', 2) ?: 'NA';
        $yearMonth = now()->format('ym');
        $prefix = "{$orgCode}-{$catCode}-{$yearMonth}";

        $latest = Product::withTrashed()
            ->where('tenant_id', $tenantId)
            ->where('sku', 'like', $prefix . '-%')
            ->orderByDesc('sku')
            ->value('sku');

        $sequence = $latest ? ((int) substr((string) $latest, -6)) + 1 : 1;

        return $prefix . '-' . str_pad((string) $sequence, 6, '0', STR_PAD_LEFT);
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
                    $suffix .= '-' . implode('-', $codes);
                }

                continue;
            }

            $definition = Definition::query()->where('code', (string) $code)->first();

            if (! $definition) {
                $suffix .= '-' . $this->segment((string) $value, 6);

                continue;
            }

            if ($definition->value_type === 'number') {
                $suffix .= '-' . $this->segment((string) $value, 4) . $this->unitCode($code, $configDetails);

                continue;
            }

            $option = Option::query()
                ->where('definition_id', $definition->id)
                ->where('name', (string) $value)
                ->first();

            $suffix .= '-' . $this->segment($option?->code ?? (string) $value, 6);
        }

        if ($suffix === '') {
            $count = Variant::withTrashed()->where('product_id', $product->id)->count();
            $suffix = '-' . ($count + 1);
        }

        $sku = $product->sku . $suffix;

        // Safety net: never exceed the DB column length (max:100).
        if (strlen($sku) > 100) {
            $sku = $product->sku . '-' . substr(md5($sku), 0, 8);
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
