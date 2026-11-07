<?php

namespace App\Http\Requests\V1\Shipping;

use App\Models\ShippingRate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Shape and cross-field rules for delivery rates.
 *
 * A tiered rate must carry at least one bracket, and brackets may not overlap —
 * an ambiguous weight would otherwise price inconsistently depending on row
 * order.
 */
trait ValidatesShippingRate
{
    /**
     * @return array<string, mixed>
     */
    protected function rateRules(bool $partial): array
    {
        $presence = $partial ? 'sometimes' : null;

        $wrap = fn (array $rules): array => $presence === null
            ? $rules
            : array_merge([$presence], $rules);

        return [
            'name' => $wrap(['required', 'string', 'max:150']),
            'code' => $wrap(['nullable', 'string', 'max:80', 'alpha_dash']),
            'description' => $wrap(['nullable', 'string', 'max:2000']),
            'shipping_zone_id' => $wrap(['nullable', 'integer', 'exists:shipping_zones,id']),
            'warehouse_id' => $wrap([
                'nullable',
                'integer',
                Rule::exists('warehouses', 'id')->where(function ($query) {
                    $tenantId = $this->tenantId();

                    $query->whereNull('tenant_id');

                    if ($tenantId !== null) {
                        $query->orWhere('tenant_id', $tenantId);
                    }
                }),
            ]),
            'tenant_id' => $wrap(['nullable', 'integer', 'exists:tenants,id']),

            'carrier' => $wrap(['nullable', 'string', 'max:30']),
            'calculation' => $wrap([
                'required',
                Rule::in([
                    ShippingRate::CALC_FLAT,
                    ShippingRate::CALC_PER_WEIGHT,
                    ShippingRate::CALC_TIERED,
                ]),
            ]),
            'currency' => $wrap(['required', 'string', 'size:3']),

            'base_amount' => $wrap(['nullable', 'numeric', 'min:0', 'max:9999999']),
            'included_weight_kg' => $wrap(['nullable', 'numeric', 'min:0', 'max:100000']),
            'per_kg_amount' => $wrap(['nullable', 'numeric', 'min:0', 'max:9999999']),

            'min_weight_kg' => $wrap(['nullable', 'numeric', 'min:0', 'max:100000']),
            'max_weight_kg' => $wrap([
                'nullable',
                'numeric',
                'min:0',
                'max:100000',
                'gte:min_weight_kg',
            ]),

            'free_over_amount' => $wrap(['nullable', 'numeric', 'min:0', 'max:9999999']),

            'estimated_days_min' => $wrap(['nullable', 'integer', 'min:0', 'max:365']),
            'estimated_days_max' => $wrap([
                'nullable',
                'integer',
                'min:0',
                'max:365',
                'gte:estimated_days_min',
            ]),

            'priority' => $wrap(['nullable', 'integer', 'min:0', 'max:65535']),
            'is_active' => $wrap(['boolean']),
            'carries_hazmat' => $wrap(['boolean']),
            'metadata' => $wrap(['nullable', 'array']),

            'tiers' => $wrap(['nullable', 'array', 'max:100']),
            'tiers.*.min_weight_kg' => ['required_with:tiers', 'numeric', 'min:0', 'max:100000'],
            'tiers.*.max_weight_kg' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'tiers.*.amount' => ['required_with:tiers', 'numeric', 'min:0', 'max:9999999'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $calculation = $this->input('calculation');
            $tiers = (array) $this->input('tiers', []);

            if ($calculation === ShippingRate::CALC_TIERED && $tiers === []) {
                $validator->errors()->add(
                    'tiers',
                    'A tiered rate needs at least one weight bracket.',
                );
            }

            $this->validateTiers($validator, $tiers);
        });
    }

    /**
     * @param  array<int|string, mixed>  $tiers
     */
    private function validateTiers(Validator $validator, array $tiers): void
    {
        if ($tiers === []) {
            return;
        }

        $rows = [];

        foreach ($tiers as $index => $tier) {
            if (! is_array($tier)) {
                continue;
            }

            $min = (float) ($tier['min_weight_kg'] ?? 0);
            $max = data_get($tier, 'max_weight_kg');

            if ($max !== null && (float) $max <= $min) {
                $validator->errors()->add(
                    "tiers.{$index}.max_weight_kg",
                    'The upper bound must be greater than the lower bound.',
                );
            }

            $rows[] = [
                'index' => $index,
                'min' => $min,
                'max' => $max === null ? INF : (float) $max,
            ];
        }

        usort($rows, fn (array $a, array $b) => $a['min'] <=> $b['min']);

        $previousMax = null;

        foreach ($rows as $row) {
            if ($previousMax !== null && $row['min'] < $previousMax) {
                $validator->errors()->add(
                    "tiers.{$row['index']}.min_weight_kg",
                    'Weight brackets may not overlap.',
                );
            }

            $previousMax = $row['max'];
        }
    }
}
