<?php

namespace App\Services\Shipping;

use App\Models\PackagingTemplate;
use App\Models\Shipment;
use App\Support\Shipping\PackedBox;
use App\Support\Shipping\PackingPlan;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * First-fit-decreasing packing into the tenant's packaging templates.
 *
 * Rules that are always enforced, because they are compliance, not preference:
 * - `ships_alone` items get a box to themselves.
 * - Dangerous goods never share a box with anything of another hazard class,
 *   nor with non-hazardous goods. An item with an unknown class is boxed alone.
 * - A box never exceeds its template's volume or maximum payload.
 *
 * Oversized items fall back to a custom box sized to the item, with a warning;
 * the operator can still adjust the boxes afterwards.
 */
class PackingPlanner
{
    private const MAX_UNITS = 300;

    private const EPSILON = 0.0005;

    public function plan(Shipment $shipment): PackingPlan
    {
        $shipment->loadMissing([
            'items.orderItem.product.shipping',
            'items.orderItem.variant.shipping',
        ]);

        $templates = $this->templates($shipment);
        $warnings = [];
        $units = $this->units($shipment, $warnings);

        if ($units === []) {
            return new PackingPlan([], ['There is nothing to pack.']);
        }

        $general = [];
        $alone = [];
        $hazmatGroups = [];
        $unknownHazmat = [];

        foreach ($units as $unit) {
            if ($unit['hazmat'] === -1) {
                $unknownHazmat[] = $unit;
            } elseif ($unit['hazmat'] !== null) {
                $hazmatGroups[(int) $unit['hazmat']][] = $unit;
            } elseif ($unit['own_box']) {
                $alone[] = $unit;
            } else {
                $general[] = $unit;
            }
        }

        $boxes = [];

        if ($general !== []) {
            $boxes = array_merge($boxes, $this->packGroup($general, null, $templates, $warnings));
        }

        foreach ($hazmatGroups as $hazardClass => $group) {
            $boxes = array_merge(
                $boxes,
                $this->packGroup($group, $hazardClass, $templates, $warnings),
            );
        }

        foreach ($unknownHazmat as $unit) {
            $boxes = array_merge($boxes, $this->packGroup([$unit], -1, $templates, $warnings));
        }

        foreach ($alone as $unit) {
            $boxes = array_merge($boxes, $this->packGroup([$unit], null, $templates, $warnings));
        }

        return new PackingPlan(
            $this->toPackedBoxes($boxes),
            array_values(array_unique($warnings)),
        );
    }

    /**
     * @param  array<int, array<string, mixed>>  $units
     * @param  Collection<int, PackagingTemplate>  $templates
     * @param  string[]  $warnings
     * @return array<int, array<string, mixed>>
     */
    private function packGroup(array $units, ?int $hazardClass, Collection $templates, array &$warnings): array
    {
        usort(
            $units,
            fn (array $a, array $b) => [$b['volume'], $b['weight']] <=> [$a['volume'], $a['weight']],
        );

        $boxes = [];

        foreach ($units as $unit) {
            $placed = false;

            foreach ($boxes as $index => $box) {
                if ($box['capacity'] === null) {
                    continue;
                }

                if ($box['used'] + $unit['volume'] > $box['capacity'] + self::EPSILON) {
                    continue;
                }

                if ($box['max_weight'] !== null
                    && $box['weight'] + $unit['weight'] > $box['max_weight'] + self::EPSILON) {
                    continue;
                }

                $boxes[$index]['used'] += $unit['volume'];
                $boxes[$index]['weight'] += $unit['weight'];
                $boxes[$index]['items'][$unit['order_item_id']] =
                    ($boxes[$index]['items'][$unit['order_item_id']] ?? 0.0) + $unit['quantity'];

                $placed = true;
                break;
            }

            if ($placed) {
                continue;
            }

            $template = $this->smallestTemplate($templates, $unit);

            if ($template === null) {
                $warnings[] = sprintf(
                    'No packaging template fits "%s"; a custom box was created.',
                    $unit['label'],
                );
            }

            $boxes[] = [
                'template' => $template,
                'capacity' => $template?->volumeCm3(),
                'max_weight' => $template?->max_weight_kg !== null
                    ? (float) $template->max_weight_kg
                    : null,
                'used' => $unit['volume'],
                'weight' => $unit['weight'],
                'items' => [$unit['order_item_id'] => $unit['quantity']],
                'hazmat' => $hazardClass,
                'dims' => $template !== null
                    ? [$template->length_cm, $template->width_cm, $template->height_cm]
                    : ($unit['dims'] ?? [null, null, null]),
            ];
        }

        return $boxes;
    }

    /**
     * @param  Collection<int, PackagingTemplate>  $templates
     * @param  array<string, mixed>  $unit
     */
    private function smallestTemplate(Collection $templates, array $unit): ?PackagingTemplate
    {
        return $templates->first(function (PackagingTemplate $template) use ($unit): bool {
            $volume = $template->volumeCm3();

            if ($volume !== null && $unit['volume'] > $volume + self::EPSILON) {
                return false;
            }

            return $template->max_weight_kg === null
                || $unit['weight'] <= (float) $template->max_weight_kg + self::EPSILON;
        });
    }

    /**
     * @param  array<int, array<string, mixed>>  $boxes
     * @return PackedBox[]
     */
    private function toPackedBoxes(array $boxes): array
    {
        return array_map(function (array $box): PackedBox {
            /** @var PackagingTemplate|null $template */
            $template = $box['template'];
            $tare = $template?->tare_weight_kg !== null ? (float) $template->tare_weight_kg : 0.0;

            $items = [];

            foreach ($box['items'] as $orderItemId => $quantity) {
                $items[] = [
                    'order_item_id' => (int) $orderItemId,
                    'quantity' => round((float) $quantity, 3),
                ];
            }

            return new PackedBox(
                templateId: $template?->id,
                templateName: $template?->name,
                lengthCm: $this->floatOrNull($box['dims'][0] ?? null),
                widthCm: $this->floatOrNull($box['dims'][1] ?? null),
                heightCm: $this->floatOrNull($box['dims'][2] ?? null),
                weightKg: round((float) $box['weight'] + $tare, 3),
                containsHazmat: $box['hazmat'] !== null,
                hazardClassOptionId: $box['hazmat'],
                items: $items,
            );
        }, $boxes);
    }

    /**
     * Expand the shipment's lines into individual units the packer can place.
     *
     * @param  string[]  $warnings
     * @return array<int, array<string, mixed>>
     */
    private function units(Shipment $shipment, array &$warnings): array
    {
        $units = [];
        $total = 0;

        foreach ($shipment->items as $item) {
            $orderItem = $item->orderItem;

            if ($orderItem === null) {
                $warnings[] = 'A deleted order line could not be packed.';

                continue;
            }

            $quantity = (float) $item->quantity;

            if ($quantity <= 0) {
                continue;
            }

            $product = $orderItem->product;
            $productShipping = $product?->shipping;
            $variantShipping = $orderItem->variant?->shipping;

            $shipping = $variantShipping?->gross_weight_kg !== null
                ? $variantShipping
                : $productShipping;

            $weight = $shipping?->gross_weight_kg !== null ? (float) $shipping->gross_weight_kg : 0.0;
            $dims = $this->dims($shipping);
            $volume = $dims !== null ? $dims[0] * $dims[1] * $dims[2] : 0.0;

            $hazmat = null;

            if ($productShipping?->is_hazmat) {
                $hazmat = $productShipping->hazard_class_option_id ?? -1;
            }

            $ownBox = (bool) $productShipping?->ships_alone || $productShipping?->is_hazmat === true;
            $label = $orderItem->name ?? 'an item';

            if ($weight <= 0) {
                $warnings[] = sprintf('"%s" has no recorded weight; it was packed by volume only.', $label);
            }

            if ($dims === null) {
                $warnings[] = sprintf('"%s" has no recorded dimensions; it was packed by weight only.', $label);
            }

            $pieces = max(1, (int) ceil($quantity));
            $total += $pieces;

            if ($total > self::MAX_UNITS) {
                throw ValidationException::withMessages([
                    'shipment' => 'Too many units to auto-pack; add boxes manually.',
                ]);
            }

            $perUnitQuantity = $quantity / $pieces;

            for ($i = 0; $i < $pieces; $i++) {
                $units[] = [
                    'order_item_id' => (int) $orderItem->id,
                    'quantity' => $perUnitQuantity,
                    'weight' => $weight,
                    'volume' => $volume,
                    'dims' => $dims,
                    'hazmat' => $hazmat,
                    'own_box' => $ownBox,
                    'label' => $label,
                ];
            }
        }

        return $units;
    }

    /**
     * @return array{0: float, 1: float, 2: float}|null
     */
    private function dims(mixed $shipping): ?array
    {
        if ($shipping === null
            || $shipping->length_cm === null
            || $shipping->width_cm === null
            || $shipping->height_cm === null) {
            return null;
        }

        return [
            (float) $shipping->length_cm,
            (float) $shipping->width_cm,
            (float) $shipping->height_cm,
        ];
    }

    /**
     * @return Collection<int, PackagingTemplate>
     */
    private function templates(Shipment $shipment): Collection
    {
        return PackagingTemplate::query()
            ->where('is_active', true)
            ->where(function ($query) use ($shipment) {
                $query->whereNull('tenant_id');

                if ($shipment->tenant_id !== null) {
                    $query->orWhere('tenant_id', $shipment->tenant_id);
                }
            })
            ->get()
            ->filter(fn (PackagingTemplate $template) => $template->volumeCm3() !== null)
            ->sortBy(fn (PackagingTemplate $template) => $template->volumeCm3())
            ->values();
    }

    private function floatOrNull(mixed $value): ?float
    {
        return $value === null ? null : (float) $value;
    }
}
