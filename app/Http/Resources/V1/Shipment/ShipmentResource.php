<?php

namespace App\Http\Resources\V1\Shipment;

use App\Enums\ShipmentStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

class ShipmentResource extends JsonResource
{
    private const QUANTITY_EPSILON = 0.0005;

    public function toArray(Request $request): array
    {
        $parcels = $this->relationLoaded('parcels') ? $this->parcels : collect();
        $items = $this->relationLoaded('items') ? $this->items : collect();

        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'order_id' => $this->order_id,
            'order' => $this->whenLoaded('order', fn () => $this->order ? [
                'uuid' => $this->order->uuid,
                'order_number' => $this->order->order_number,
            ] : null),

            'warehouse_id' => $this->warehouse_id,
            'warehouse' => $this->whenLoaded('warehouse', fn () => $this->warehouse ? [
                'id' => $this->warehouse->id,
                'uuid' => $this->warehouse->uuid,
                'name' => $this->warehouse->name,
                'city' => $this->warehouse->city,
            ] : null),

            'status' => $this->status,
            'status_label' => ShipmentStatus::label((string) $this->status),
            'is_open' => ShipmentStatus::isOpen((string) $this->status),

            'carrier' => $this->carrier,
            'service' => $this->service,
            'tracking_number' => $this->tracking_number,
            'tracking_url' => $this->tracking_url,
            'carrier_booking_id' => $this->carrier_booking_id,
            'label_url' => $this->label_url,
            'booked_at' => $this->booked_at,
            'weight_kg' => $this->weight_kg !== null ? (float) $this->weight_kg : null,

            'ship_to' => $this->ship_to,
            'ship_from' => $this->ship_from,
            'notes' => $this->notes,

            'contains_hazmat' => $parcels->contains(fn ($parcel) => (bool) $parcel->contains_hazmat),
            'packed' => $this->packedLines($parcels),
            'unpacked' => $this->hasUnpackedLines($items, $parcels),

            'parcel_count' => $parcels->count(),
            'parcels' => $parcels->map(fn ($parcel) => [
                'id' => $parcel->id,
                'uuid' => $parcel->uuid,
                'sequence' => $parcel->sequence,
                'packaging_template_id' => $parcel->packaging_template_id,
                'packaging_template' => $parcel->relationLoaded('packagingTemplate') && $parcel->packagingTemplate
                    ? [
                        'id' => $parcel->packagingTemplate->id,
                        'uuid' => $parcel->packagingTemplate->uuid,
                        'name' => $parcel->packagingTemplate->name,
                    ]
                    : null,
                'length_cm' => $parcel->length_cm !== null ? (float) $parcel->length_cm : null,
                'width_cm' => $parcel->width_cm !== null ? (float) $parcel->width_cm : null,
                'height_cm' => $parcel->height_cm !== null ? (float) $parcel->height_cm : null,
                'volume_cm3' => $parcel->volumeCm3(),
                'weight_kg' => $parcel->weight_kg !== null ? (float) $parcel->weight_kg : null,
                'contains_hazmat' => (bool) $parcel->contains_hazmat,
                'tracking_number' => $parcel->tracking_number,
                'notes' => $parcel->notes,
                'items' => $parcel->relationLoaded('items')
                    ? $parcel->items->map(fn ($line) => [
                        'id' => $line->id,
                        'order_item_id' => $line->order_item_id,
                        'name' => $line->relationLoaded('orderItem') ? $line->orderItem?->name : null,
                        'sku' => $line->relationLoaded('orderItem') ? $line->orderItem?->sku : null,
                        'quantity' => (float) $line->quantity,
                    ])->values()->all()
                    : [],
            ])->values()->all(),

            'items' => $items->map(fn ($item) => [
                'id' => $item->id,
                'order_item_id' => $item->order_item_id,
                'name' => $item->relationLoaded('orderItem') ? $item->orderItem?->name : null,
                'sku' => $item->relationLoaded('orderItem') ? $item->orderItem?->sku : null,
                'quantity' => (float) $item->quantity,
            ])->values()->all(),

            'packed_at' => $this->packed_at,
            'shipped_at' => $this->shipped_at,
            'delivered_at' => $this->delivered_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    /**
     * @param  Collection<int, mixed>  $parcels
     * @return array<int, float>
     */
    private function packedQuantities($parcels): array
    {
        $packed = [];

        foreach ($parcels as $parcel) {
            if (! $parcel->relationLoaded('items')) {
                continue;
            }

            foreach ($parcel->items as $line) {
                $id = (int) $line->order_item_id;
                $packed[$id] = ($packed[$id] ?? 0.0) + (float) $line->quantity;
            }
        }

        $rounded = [];

        foreach ($packed as $id => $quantity) {
            $rounded[(int) $id] = round($quantity, 3);
        }

        return $rounded;
    }

    /**
     * How much of each order line is currently inside a box, as a list so the
     * shape is stable regardless of order-item ids.
     *
     * @param  Collection<int, mixed>  $parcels
     * @return array<int, array{order_item_id: int, quantity: float}>
     */
    private function packedLines($parcels): array
    {
        $lines = [];

        foreach ($this->packedQuantities($parcels) as $orderItemId => $quantity) {
            $lines[] = [
                'order_item_id' => (int) $orderItemId,
                'quantity' => $quantity,
            ];
        }

        return $lines;
    }

    /**
     * True while any shipment line is not (fully) accounted for by the boxes.
     *
     * @param  Collection<int, mixed>  $items
     * @param  Collection<int, mixed>  $parcels
     */
    private function hasUnpackedLines($items, $parcels): bool
    {
        if ($items->isEmpty()) {
            return false;
        }

        $packed = $this->packedQuantities($parcels);

        foreach ($items as $item) {
            $quantity = (float) $item->quantity;

            if (($packed[(int) $item->order_item_id] ?? 0.0) + self::QUANTITY_EPSILON < $quantity) {
                return true;
            }
        }

        return false;
    }
}
