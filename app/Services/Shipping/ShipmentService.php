<?php

namespace App\Services\Shipping;

use App\Enums\OrderStatus;
use App\Enums\ShipmentStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PackagingTemplate;
use App\Models\Shipment;
use App\Models\ShipmentItem;
use App\Models\Warehouse;
use App\Services\Order\OrderService;
use App\Support\Shipping\PackedBox;
use App\Support\Shipping\PackingPlan;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Fulfilment of an order into one or more shipments.
 *
 * Quantities are checked against every *other* non-cancelled shipment inside
 * the transaction (with a row lock on the order lines), so two operators packing
 * the same order concurrently cannot dispatch more units than were sold.
 *
 * Phase 5 adds physical contents: a box records which order lines it carries
 * (`parcel_items`) and whether any of them is dangerous goods. The packing
 * planner is only a suggestion — every box it proposes is written through the
 * same validation an operator's boxes go through.
 */
class ShipmentService
{
    private const QUANTITY_EPSILON = 0.0005;

    public function __construct(
        protected OrderService $orders,
        protected PackingPlanner $planner,
    ) {}

    /**
     * @return Collection<int, Shipment>
     */
    public function listForOrder(Order $order): Collection
    {
        return $order->shipments()
            ->with($this->detailRelations())
            ->get();
    }

    /**
     * Everything the pick-pack screen needs: existing shipments plus how much
     * of each order line is already allocated.
     *
     * @return array{shipments: Collection<int, Shipment>, items: array<int, array<string, mixed>>, can_ship: bool}
     */
    public function planForOrder(Order $order): array
    {
        $order->loadMissing('items');

        $shipments = $this->listForOrder($order);
        $allocated = $this->allocatedByOrderItem($shipments);

        $items = $order->items->map(function (OrderItem $item) use ($allocated): array {
            $shipped = round($allocated[(int) $item->id] ?? 0.0, 3);

            return [
                'id' => $item->id,
                'uuid' => $item->uuid,
                'name' => $item->name,
                'sku' => $item->sku,
                'quantity' => (float) $item->quantity,
                'shipped' => $shipped,
                'remaining' => round(max(0.0, (float) $item->quantity - $shipped), 3),
            ];
        })->values()->all();

        $hasRemaining = collect($items)->contains(
            fn (array $item) => $item['remaining'] > self::QUANTITY_EPSILON,
        );

        return [
            'shipments' => $shipments,
            'items' => $items,
            'can_ship' => $hasRemaining
                && ! in_array($order->status, [OrderStatus::CANCELLED, OrderStatus::REFUNDED], true),
        ];
    }

    public function show(string $uuid): Shipment
    {
        return Shipment::query()
            ->with(array_merge(['order'], $this->detailRelations()))
            ->where('uuid', $uuid)
            ->firstOrFail();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(Order $order, array $data): Shipment
    {
        if (in_array($order->status, [OrderStatus::CANCELLED, OrderStatus::REFUNDED], true)) {
            throw ValidationException::withMessages([
                'order' => 'A cancelled order cannot be shipped.',
            ]);
        }

        $items = $data['items'] ?? [];
        $parcels = $data['parcels'] ?? [];
        $autoPack = (bool) ($data['auto_pack'] ?? false);
        $attributes = $this->shipmentAttributes($data, $order);

        return DB::transaction(function () use ($order, $attributes, $items, $parcels, $autoPack) {
            $shipment = Shipment::query()->create(array_merge($attributes, [
                'order_id' => $order->id,
                'tenant_id' => $order->tenant_id,
                'status' => ShipmentStatus::PENDING,
                'created_by' => auth()->id(),
                // Snapshot where the parcel is going at dispatch time.
                'ship_to' => $order->shipping_address,
            ]));

            $this->syncItems($shipment, $order, $items);

            if ($autoPack) {
                $this->applyPlanTo($shipment);
            } else {
                $this->syncParcels($shipment, $parcels);
            }

            $this->persistWeight($shipment);

            return $this->show($shipment->uuid);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Shipment $shipment, array $data): Shipment
    {
        $touchesContents = array_key_exists('items', $data) || array_key_exists('parcels', $data);

        if (! $shipment->isOpen() && $touchesContents) {
            throw ValidationException::withMessages([
                'shipment' => 'A dispatched shipment can no longer be edited.',
            ]);
        }

        return DB::transaction(function () use ($shipment, $data) {
            $hasItems = array_key_exists('items', $data);
            $hasParcels = array_key_exists('parcels', $data);
            $items = $data['items'] ?? [];
            $parcels = $data['parcels'] ?? [];

            $shipment->fill($this->shipmentAttributes($data, $shipment->order))->save();

            if ($hasItems) {
                $this->syncItems($shipment, $shipment->order, $items);
            }

            if ($hasParcels) {
                $this->syncParcels($shipment, $parcels);
            }

            $this->persistWeight($shipment);

            return $this->show($shipment->uuid);
        });
    }

    /**
     * The boxes the planner suggests for this shipment, without writing them.
     *
     * @return array<string, mixed>
     */
    public function packingPlan(Shipment $shipment): array
    {
        return $this->planner->plan($shipment)->toArray();
    }

    /**
     * Replace the shipment's boxes with the planner's suggestion.
     *
     * Idempotent: applying it twice leaves the same boxes, because the planner
     * always derives them from the shipment's lines.
     *
     * @return array{shipment: Shipment, plan: array<string, mixed>}
     */
    public function applyPlan(Shipment $shipment): array
    {
        if (! $shipment->isOpen()) {
            throw ValidationException::withMessages([
                'shipment' => 'A dispatched shipment can no longer be re-packed.',
            ]);
        }

        return DB::transaction(function () use ($shipment) {
            $plan = $this->planner->plan($shipment);

            $this->syncParcels($shipment, $this->boxesToPayload($plan));
            $this->persistWeight($shipment);

            return [
                'shipment' => $this->show($shipment->uuid),
                'plan' => $plan->toArray(),
            ];
        });
    }

    public function changeStatus(Shipment $shipment, string $status): Shipment
    {
        $from = (string) $shipment->status;

        if (! ShipmentStatus::canTransition($from, $status)) {
            throw ValidationException::withMessages([
                'status' => "A {$from} shipment cannot be moved to {$status}.",
            ]);
        }

        if ($status === ShipmentStatus::SHIPPED && $shipment->parcels()->count() === 0) {
            throw ValidationException::withMessages([
                'status' => 'Record at least one parcel before marking the shipment as shipped.',
            ]);
        }

        $attributes = ['status' => $status];

        if ($status === ShipmentStatus::PACKED && $shipment->packed_at === null) {
            $attributes['packed_at'] = now();
        }

        if ($status === ShipmentStatus::SHIPPED && $shipment->shipped_at === null) {
            $attributes['shipped_at'] = now();
        }

        if ($status === ShipmentStatus::DELIVERED && $shipment->delivered_at === null) {
            $attributes['delivered_at'] = now();
        }

        return DB::transaction(function () use ($shipment, $attributes) {
            $shipment->fill($attributes)->save();

            $this->syncOrderStatus($shipment->fresh());

            return $this->show($shipment->uuid);
        });
    }

    public function delete(Shipment $shipment): bool
    {
        if (! $shipment->isOpen()) {
            throw ValidationException::withMessages([
                'shipment' => 'A dispatched shipment cannot be deleted.',
            ]);
        }

        return (bool) $shipment->delete();
    }

    /**
     * Replace this shipment's lines. `items.N.*` validation errors point at the
     * request field the operator can fix.
     *
     * @param  array<int, array<string, mixed>>  $items
     */
    private function syncItems(Shipment $shipment, Order $order, array $items): void
    {
        $shipment->items()->delete();
        $shipment->unsetRelation('items');

        if ($items === []) {
            return;
        }

        $ids = array_map(fn (array $row) => (int) $row['order_item_id'], $items);

        // Row-locked: the remaining quantity must not move between the check
        // and the insert.
        $orderItems = OrderItem::query()
            ->whereIn('id', $ids)
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        $allocated = ShipmentItem::query()
            ->whereIn('order_item_id', $ids)
            ->where('shipment_id', '!=', $shipment->id)
            ->whereHas('shipment', fn ($query) => $query->where('status', '!=', ShipmentStatus::CANCELLED))
            ->selectRaw('order_item_id, SUM(quantity) as shipped')
            ->groupBy('order_item_id')
            ->pluck('shipped', 'order_item_id');

        foreach ($items as $index => $row) {
            $orderItem = $orderItems->get((int) $row['order_item_id']);

            if ($orderItem === null || (int) $orderItem->order_id !== (int) $order->id) {
                throw ValidationException::withMessages([
                    "items.{$index}.order_item_id" => 'This item does not belong to the order.',
                ]);
            }

            $quantity = round((float) $row['quantity'], 3);
            $remaining = round(
                (float) $orderItem->quantity - (float) ($allocated[$orderItem->id] ?? 0.0),
                3,
            );

            if ($quantity > $remaining + self::QUANTITY_EPSILON) {
                throw ValidationException::withMessages([
                    "items.{$index}.quantity" => "Only {$remaining} unit(s) of this item remain unshipped.",
                ]);
            }

            $shipment->items()->create([
                'order_item_id' => $orderItem->id,
                'quantity' => $quantity,
            ]);
        }
    }

    /**
     * Replace the boxes. Dimensions fall back to the chosen template, so the
     * operator only has to enter what the template does not know (the actual
     * packed weight).
     *
     * Box contents are preserved when the payload omits `items`, which is the
     * common case when an operator only corrects a weight. Supplying `items`
     * for any box makes the whole payload authoritative.
     *
     * @param  array<int, array<string, mixed>>  $parcels
     */
    private function syncParcels(Shipment $shipment, array $parcels): void
    {
        $assignments = $this->parcelAssignments($shipment, $parcels);

        $this->assertParcelQuantities($shipment, $assignments);

        $shipment->parcels()->delete();

        if ($parcels === []) {
            return;
        }

        $templateIds = array_values(array_filter(array_map(
            fn (array $row) => isset($row['packaging_template_id'])
                ? (int) $row['packaging_template_id']
                : null,
            $parcels,
        )));

        $templates = $templateIds === []
            ? collect()
            : PackagingTemplate::query()->whereIn('id', $templateIds)->get()->keyBy('id');

        $hazmat = $this->hazmatFlags($shipment);

        foreach ($parcels as $index => $row) {
            $template = $templates->get((int) ($row['packaging_template_id'] ?? 0));
            $lines = $assignments[$index] ?? [];

            $parcel = $shipment->parcels()->create([
                'packaging_template_id' => $template?->id,
                'sequence' => (int) ($row['sequence'] ?? $index + 1),
                'length_cm' => $row['length_cm'] ?? $template?->length_cm,
                'width_cm' => $row['width_cm'] ?? $template?->width_cm,
                'height_cm' => $row['height_cm'] ?? $template?->height_cm,
                'weight_kg' => $row['weight_kg'] ?? null,
                'contains_hazmat' => (bool) ($row['contains_hazmat'] ?? false)
                    || $this->linesContainHazmat($lines, $hazmat),
                'tracking_number' => $row['tracking_number'] ?? null,
                'notes' => $row['notes'] ?? null,
            ]);

            foreach ($lines as $line) {
                $parcel->items()->create([
                    'order_item_id' => (int) $line['order_item_id'],
                    'quantity' => round((float) $line['quantity'], 3),
                ]);
            }
        }

        $shipment->unsetRelation('parcels');
    }

    /**
     * Work out what goes into each box, falling back to the assignments already
     * stored on the matching box when the payload only updates dimensions.
     *
     * @param  array<int, array<string, mixed>>  $parcels
     * @return array<int, array<int, array<string, mixed>>>
     */
    private function parcelAssignments(Shipment $shipment, array $parcels): array
    {
        $existing = $shipment->parcels()
            ->with('items')
            ->get()
            ->keyBy('sequence');

        $assignments = [];

        foreach ($parcels as $index => $row) {
            if (array_key_exists('items', $row)) {
                $assignments[$index] = array_values((array) $row['items']);

                continue;
            }

            $sequence = (int) ($row['sequence'] ?? $index + 1);
            $parcel = $existing->get($sequence);

            $assignments[$index] = $parcel === null
                ? []
                : $parcel->items->map(fn ($item) => [
                    'order_item_id' => (int) $item->order_item_id,
                    'quantity' => (float) $item->quantity,
                ])->values()->all();
        }

        return $assignments;
    }

    /**
     * A line may span boxes, but the boxes of one shipment may never carry more
     * than the shipment itself does.
     *
     * @param  array<int, array<int, array<string, mixed>>>  $assignments
     */
    private function assertParcelQuantities(Shipment $shipment, array $assignments): void
    {
        $shipment->loadMissing('items');

        $limits = $shipment->items
            ->mapWithKeys(fn (ShipmentItem $item) => [(int) $item->order_item_id => (float) $item->quantity])
            ->all();

        $totals = [];
        $locations = [];

        foreach ($assignments as $parcelsIndex => $lines) {
            $seen = [];

            foreach ($lines as $lineIndex => $line) {
                $orderItemId = (int) ($line['order_item_id'] ?? 0);

                if (isset($seen[$orderItemId])) {
                    throw ValidationException::withMessages([
                        "parcels.{$parcelsIndex}.items.{$lineIndex}.order_item_id" => 'An order line may only appear once per box.',
                    ]);
                }

                $seen[$orderItemId] = true;

                if (! array_key_exists($orderItemId, $limits)) {
                    throw ValidationException::withMessages([
                        "parcels.{$parcelsIndex}.items.{$lineIndex}.order_item_id" => 'This item is not part of the shipment.',
                    ]);
                }

                $totals[$orderItemId] = ($totals[$orderItemId] ?? 0.0) + (float) ($line['quantity'] ?? 0);
                // Point at the most recent box, which is where the overage occurs.
                $locations[$orderItemId] = "parcels.{$parcelsIndex}.items.{$lineIndex}.quantity";
            }
        }

        foreach ($totals as $orderItemId => $total) {
            if ($total > $limits[$orderItemId] + self::QUANTITY_EPSILON) {
                throw ValidationException::withMessages([
                    $locations[$orderItemId] => 'The boxes contain more units than the shipment carries.',
                ]);
            }
        }
    }

    /**
     * True when any of the lines in a box is dangerous goods.
     *
     * @param  array<int, array<string, mixed>>  $lines
     * @param  array<int, bool>  $hazmat
     */
    private function linesContainHazmat(array $lines, array $hazmat): bool
    {
        foreach ($lines as $line) {
            if ($hazmat[(int) ($line['order_item_id'] ?? 0)] ?? false) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<int, bool> Keyed by order item id.
     */
    private function hazmatFlags(Shipment $shipment): array
    {
        $shipment->loadMissing('items.orderItem.product.shipping');

        $flags = [];

        foreach ($shipment->items as $item) {
            $flags[(int) $item->order_item_id] = (bool) $item->orderItem?->product?->shipping?->is_hazmat;
        }

        return $flags;
    }

    /**
     * Write the planner's boxes with the same validation as manual parcels.
     *
     * @return array<int, array<string, mixed>>
     */
    private function boxesToPayload(PackingPlan $plan): array
    {
        return array_map(fn (PackedBox $box, int $index) => [
            'sequence' => $index + 1,
            'packaging_template_id' => $box->templateId,
            'length_cm' => $box->lengthCm,
            'width_cm' => $box->widthCm,
            'height_cm' => $box->heightCm,
            'weight_kg' => $box->weightKg,
            'contains_hazmat' => $box->containsHazmat,
            'items' => $box->items,
        ], $plan->boxes, array_keys($plan->boxes));
    }

    private function applyPlanTo(Shipment $shipment): void
    {
        $this->syncParcels($shipment, $this->boxesToPayload($this->planner->plan($shipment)));
    }

    private function persistWeight(Shipment $shipment): void
    {
        $shipment->unsetRelation('parcels');
        $shipment->load('parcels');
        $shipment->recalculateWeight();
        $shipment->save();
    }

    /**
     * Move the order itself forward once every line is fully dispatched or
     * delivered. COD capture on delivery stays in `OrderService`.
     */
    private function syncOrderStatus(Shipment $shipment): void
    {
        $order = $shipment->order()->with(['items', 'shipments.items'])->first();

        if ($order === null || in_array($order->status, [OrderStatus::CANCELLED, OrderStatus::REFUNDED], true)) {
            return;
        }

        $active = $order->shipments->where('status', '!=', ShipmentStatus::CANCELLED);
        $allocated = $this->allocatedByOrderItem($active);

        $fullyAllocated = $order->items->every(
            fn (OrderItem $item) => ($allocated[(int) $item->id] ?? 0.0) + self::QUANTITY_EPSILON
                >= (float) $item->quantity,
        );

        if (! $fullyAllocated) {
            return;
        }

        if ($shipment->status === ShipmentStatus::DELIVERED) {
            $allDelivered = $active->isNotEmpty()
                && $active->every(fn (Shipment $candidate) => $candidate->status === ShipmentStatus::DELIVERED);

            if ($allDelivered && $order->status !== OrderStatus::DELIVERED) {
                $this->orders->updateStatus($order, OrderStatus::DELIVERED);
            }

            return;
        }

        if ($shipment->status === ShipmentStatus::SHIPPED
            && in_array($order->status, [
                OrderStatus::PENDING,
                OrderStatus::CONFIRMED,
                OrderStatus::PROCESSING,
                OrderStatus::ARRIVED_WAREHOUSE,
            ], true)) {
            $this->orders->updateStatus($order, OrderStatus::SHIPPED);
        }
    }

    /**
     * @param  Collection<int, Shipment>  $shipments
     * @return array<int, float>
     */
    private function allocatedByOrderItem(Collection $shipments): array
    {
        $allocated = [];

        foreach ($shipments as $shipment) {
            if ($shipment->status === ShipmentStatus::CANCELLED) {
                continue;
            }

            foreach ($shipment->items as $item) {
                $id = (int) $item->order_item_id;
                $allocated[$id] = ($allocated[$id] ?? 0.0) + (float) $item->quantity;
            }
        }

        return $allocated;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function shipmentAttributes(array $data, ?Order $order): array
    {
        $attributes = array_intersect_key($data, array_flip([
            'warehouse_id',
            'carrier',
            'service',
            'tracking_number',
            'tracking_url',
            'notes',
        ]));

        if (array_key_exists('carrier', $attributes)) {
            $attributes['carrier'] = $attributes['carrier'] ?: 'manual';
        }

        if (array_key_exists('warehouse_id', $attributes)) {
            if (filled($attributes['warehouse_id'])) {
                $warehouse = $this->resolveWarehouse((int) $attributes['warehouse_id'], $order);

                $attributes['warehouse_id'] = $warehouse->id;
                $attributes['ship_from'] = $this->originSnapshot($warehouse);
            } else {
                // Clearing the origin must not leave a stale dispatch address.
                $attributes['warehouse_id'] = null;
                $attributes['ship_from'] = null;
            }
        }

        return $attributes;
    }

    private function resolveWarehouse(int $id, ?Order $order): Warehouse
    {
        $warehouse = Warehouse::query()
            ->whereKey($id)
            ->where('is_active', true)
            ->first();

        if ($warehouse === null) {
            throw ValidationException::withMessages([
                'warehouse_id' => 'The selected warehouse is not available.',
            ]);
        }

        if ($order?->tenant_id !== null
            && $warehouse->tenant_id !== null
            && (int) $warehouse->tenant_id !== (int) $order->tenant_id) {
            throw ValidationException::withMessages([
                'warehouse_id' => 'The selected warehouse belongs to another tenant.',
            ]);
        }

        return $warehouse;
    }

    /**
     * @return array<string, mixed>
     */
    private function originSnapshot(Warehouse $warehouse): array
    {
        return array_filter([
            'contact_name' => $warehouse->contact_name,
            'phone' => $warehouse->phone,
            'email' => $warehouse->email,
            'address_line_1' => $warehouse->address_line_1,
            'address_line_2' => $warehouse->address_line_2,
            'city' => $warehouse->city,
            'state' => $warehouse->state,
            'postal_code' => $warehouse->postal_code,
            'country' => $warehouse->country,
        ], fn ($value) => $value !== null && $value !== '');
    }

    /**
     * @return array<int, string>
     */
    private function detailRelations(): array
    {
        return [
            'warehouse',
            'items.orderItem.product.shipping',
            'parcels.packagingTemplate',
            'parcels.items.orderItem',
        ];
    }
}
