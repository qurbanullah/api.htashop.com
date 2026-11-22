<?php

namespace App\Services\Punchout;

use App\Actions\Punchout\NormalizePunchoutOrderRequestAction;
use App\Actions\Punchout\ResolvePunchoutBuyerAction;
use App\Actions\Punchout\ResolvePunchoutPriceAction;
use App\Actions\Punchout\ResolvePunchoutTenantAction;
use App\Actions\Punchout\ValidatePunchoutSetupCredentialsAction;
use App\Enums\OrderStatus;
use App\Enums\SalesChannel;
use App\Models\BuyerItem;
use App\Models\BuyerItemMapping;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Price;
use App\Models\Product;
use App\Models\PunchoutBuyer;
use App\Models\PunchoutInboundOrder;
use App\Models\Tenant;
use App\Models\Variant;
use App\Support\Punchout\Data\PunchoutOrderRequestData;
use App\Support\Punchout\Data\PunchoutResponseData;
use App\Support\Punchout\Data\PunchoutSetupData;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PunchoutInboundOrderService
{
    public function __construct(
        protected ResolvePunchoutTenantAction $resolvePunchoutTenantAction,
        protected NormalizePunchoutOrderRequestAction $normalizePunchoutOrderRequestAction,
        protected ResolvePunchoutBuyerAction $resolvePunchoutBuyerAction,
        protected ValidatePunchoutSetupCredentialsAction $validatePunchoutSetupCredentialsAction,
        protected ResolvePunchoutPriceAction $resolvePunchoutPriceAction,
        protected PunchoutProtocolService $protocolService,
    ) {}

    public function handle(string $tenantIdentifier, ?string $rawBody, ?string $protocol = null): PunchoutResponseData
    {
        $tenant = $this->resolvePunchoutTenantAction->handle($tenantIdentifier);
        $protocol ??= 'cxml';
        $data = $this->normalizePunchoutOrderRequestAction->handle($protocol, $rawBody);

        $buyer = $this->resolvePunchoutBuyerAction->handle($tenant, $protocol, $data->credentials);

        if ($buyer === null && $this->resolvePunchoutBuyerAction->hasActiveBuyers($tenant, $protocol)) {
            throw ValidationException::withMessages([
                'credentials' => ['No active punchout buyer matches these credentials.'],
            ]);
        }

        if ($buyer === null) {
            $this->validatePunchoutSetupCredentialsAction->handle(
                $tenant,
                new PunchoutSetupData(
                    protocol: 'cxml',
                    operation: 'OrderRequest',
                    returnUrl: '',
                    buyerCookie: null,
                    payload: [
                        'sender' => [
                            'identity' => $data->credentials['sender_identity'] ?? null,
                            'shared_secret' => $data->credentials['shared_secret'] ?? null,
                        ],
                        'from' => ['credential' => $data->credentials['from_identity'] ?? null],
                        'to' => ['credential' => $data->credentials['to_identity'] ?? null],
                    ],
                ),
                $this->protocolService->protocolConfiguration($tenant, 'cxml'),
            );
        }

        $existing = PunchoutInboundOrder::query()
            ->where('tenant_id', $tenant->id)
            ->where('payload_id', $data->payloadId)
            ->first();

        if ($existing) {
            return $this->replayResponse($tenant, $existing);
        }

        try {
            $inbound = PunchoutInboundOrder::query()->create([
                'tenant_id' => $tenant->id,
                'buyer_id' => $buyer?->id,
                'payload_id' => $data->payloadId,
                'order_reference' => $data->orderId,
                'status' => 'received',
                'protocol' => $protocol,
                'currency' => $data->currency,
                'declared_total' => $data->declaredTotal,
                'payload' => $data->payload,
            ]);
        } catch (QueryException $exception) {
            // Two retries can race past the SELECT above; the unique index is
            // the real idempotency guarantee.
            $inbound = PunchoutInboundOrder::query()
                ->where('tenant_id', $tenant->id)
                ->where('payload_id', $data->payloadId)
                ->first();

            if (! $inbound) {
                throw $exception;
            }

            return $this->replayResponse($tenant, $inbound);
        }

        if (! $this->isSellerOfRecord($tenant)) {
            $inbound->update(['status' => 'ignored']);

            return $this->renderResponse($tenant, $inbound, null, ignored: true);
        }

        try {
            $order = DB::transaction(function () use ($tenant, $data, $buyer, $inbound): Order {
                $locked = PunchoutInboundOrder::query()->lockForUpdate()->findOrFail($inbound->id);

                if ($locked->order_id !== null) {
                    return Order::query()->findOrFail($locked->order_id);
                }

                $order = $this->createOrder($tenant, $data, $buyer);

                $locked->update([
                    'status' => 'created',
                    'order_id' => $order->id,
                    'currency' => $order->currency,
                ]);

                return $order;
            });
        } catch (ValidationException $exception) {
            $inbound->update([
                'status' => 'failed',
                'error' => $exception->getMessage(),
            ]);

            throw $exception;
        }

        return $this->renderResponse($tenant, $inbound->fresh(), $order);
    }

    /**
     * A retry must mirror the original outcome: an accepted order returns its
     * reference, an ignored order stays 202, and a previously failed payload
     * keeps failing instead of looking successful.
     */
    private function replayResponse(Tenant $tenant, PunchoutInboundOrder $inbound): PunchoutResponseData
    {
        return match ($inbound->status) {
            'created' => $this->renderResponse($tenant, $inbound, $inbound->order),
            'ignored' => $this->renderResponse($tenant, $inbound, null, ignored: true),
            'failed' => throw ValidationException::withMessages([
                'order_request' => [$inbound->error ?: 'The inbound order request previously failed.'],
            ]),
            default => $this->renderResponse($tenant, $inbound, null, ignored: true),
        };
    }

    private function isSellerOfRecord(Tenant $tenant): bool
    {
        $settings = is_array($tenant->settings) ? $tenant->settings : [];

        return (bool) data_get(
            $settings,
            'punchout.inbound_orders_enabled',
            config('punchout.inbound_orders.enabled', false),
        );
    }

    private function createOrder(Tenant $tenant, PunchoutOrderRequestData $data, ?PunchoutBuyer $buyer): Order
    {
        $resolved = [];
        $subtotal = 0.0;
        $currency = $data->currency;

        foreach ($data->lines as $line) {
            [$product, $variant, $matchedBy] = $this->matchLine($tenant, $buyer, $line);

            if (! $product) {
                $identifiers = array_filter([
                    $line['supplier_part_id'] ?? null,
                    $line['customer_part_number'] ?? null,
                    $line['manufacturer_part_id'] ?? null,
                ]);

                throw ValidationException::withMessages([
                    'items' => ["No punchout product matches part '".($identifiers === [] ? 'unknown' : implode(', ', $identifiers))."'."],
                ]);
            }

            $pricing = $this->resolvePunchoutPriceAction->handle(
                $tenant,
                $product,
                $variant,
                (float) $line['quantity'],
                $buyer,
            );

            $currency ??= $pricing['currency'];
            $lineTotal = round((float) $pricing['unit_price'] * (float) $line['quantity'], 2);
            $subtotal += $lineTotal;

            $resolved[] = [
                'product' => $product,
                'variant' => $variant,
                'pricing' => $pricing,
                'line' => $line,
                'total' => $lineTotal,
                'matched_by' => $matchedBy,
            ];
        }

        $currency = (string) ($currency ?: 'USD');

        /** @var Order $order */
        $order = Order::query()->create([
            'tenant_id' => $tenant->id,
            'organization_id' => $buyer?->organization_id,
            'customer_name' => $data->shipTo['name'] ?: ($buyer?->name ?? 'Punchout buyer'),
            'customer_email' => $data->shipTo['email'] ?: $buyer?->organization?->email,
            'status' => OrderStatus::PENDING,
            'source' => 'punchout',
            'placed_at' => now(),
            'currency' => $currency,
            'subtotal' => $subtotal,
            'shipping_fee' => 0,
            'tax' => 0,
            'discount' => 0,
            'total_amount' => $subtotal,
            'shipping_address' => array_filter($data->shipTo, fn ($value) => $value !== null && $value !== ''),
            'billing_address' => array_filter($data->shipTo, fn ($value) => $value !== null && $value !== ''),
            'notes' => 'Inbound cXML order '.($data->orderId ?? $data->payloadId),
            'metadata' => [
                'punchout' => [
                    'payload_id' => $data->payloadId,
                    'order_id' => $data->orderId,
                    'buyer_id' => $buyer?->id,
                    'buyer_code' => $buyer?->code,
                    'declared_total' => $data->declaredTotal,
                    'declared_currency' => $data->currency,
                ],
            ],
        ]);

        foreach ($resolved as $entry) {
            /** @var Product $product */
            $product = $entry['product'];
            /** @var Variant|null $variant */
            $variant = $entry['variant'];
            $pricing = $entry['pricing'];
            $line = $entry['line'];

            OrderItem::query()->create([
                'order_id' => $order->id,
                'product_id' => $product->id,
                'variant_id' => $variant?->id,
                'name' => $product->name,
                'sku' => $variant?->sku ?? $product->sku ?? $line['supplier_part_id'],
                'quantity' => $line['quantity'],
                'unit_price' => $pricing['unit_price'],
                'base_price' => $pricing['base_price'],
                'total' => $entry['total'],
                'currency' => $pricing['currency'],
                'metadata' => array_filter([
                    'supplier_part_id' => $line['supplier_part_id'],
                    'buyer_part_number' => $line['customer_part_number'] ?: $pricing['customer_part_number'],
                    'customer_part_number' => $line['customer_part_number'] ?: $pricing['customer_part_number'],
                    'manufacturer_part_id' => $line['manufacturer_part_id'] ?? null,
                    'unit_of_measure' => $line['unit_of_measure'] ?: $pricing['unit_of_measure'],
                    'line_number' => $line['line_number'],
                    'price_source' => $pricing['source'],
                    'declared_unit_price' => $line['unit_price'],
                    'matched_by' => $entry['matched_by'],
                ], fn ($value) => $value !== null),
            ]);
        }

        return $order->load('items');
    }

    /**
     * Resolve an inbound line to our product.
     *
     * Precedence: the buyer's own item number (BuyerPartID), then SupplierPartID,
     * then ManufacturerPartID, then the raw identifiers as a last resort, then
     * the transitional price metadata. Buyers occasionally put their own code in
     * SupplierPartID, and a PO may legitimately reference a code we superseded,
     * so the item-master lookup ignores `is_active` on the mapping while still
     * requiring an active punchout product.
     *
     * @param  array<string, mixed>  $line
     * @return array{0: Product|null, 1: Variant|null, 2: string|null}
     */
    private function matchLine(Tenant $tenant, ?PunchoutBuyer $buyer, array $line): array
    {
        $supplierPart = trim((string) ($line['supplier_part_id'] ?? ''));
        $buyerPart = trim((string) ($line['customer_part_number'] ?? ''));
        $manufacturerPart = trim((string) ($line['manufacturer_part_id'] ?? ''));

        if ($buyerPart !== '') {
            [$product, $variant] = $this->matchBuyerItem($tenant, $buyer, $buyerPart);

            if ($product) {
                return [$product, $variant, 'buyer_item'];
            }
        }

        if ($supplierPart !== '') {
            [$product, $variant] = $this->matchSupplierCode($tenant, $supplierPart);

            if ($product) {
                return [$product, $variant, 'supplier_part'];
            }
        }

        if ($manufacturerPart !== '') {
            $product = Product::query()
                ->where('tenant_id', $tenant->id)
                ->where('is_active', true)
                ->where('status', 'active')
                ->forChannel(SalesChannel::PUNCHOUT)
                ->where('model_number', $manufacturerPart)
                ->first();

            if ($product) {
                return [$product, null, 'manufacturer_part'];
            }
        }

        // A buyer may echo its code in SupplierPartID, or send a code that has no
        // mapping yet; try it against our own identifiers before giving up.
        if ($buyerPart !== '') {
            [$product, $variant] = $this->matchSupplierCode($tenant, $buyerPart);

            if ($product) {
                return [$product, $variant, 'supplier_part'];
            }
        }

        foreach (array_unique(array_filter([$buyerPart, $supplierPart])) as $code) {
            [$product, $variant] = $this->matchLegacyPrice($tenant, $code);

            if ($product) {
                return [$product, $variant, 'legacy_price'];
            }
        }

        return [null, null, null];
    }

    /**
     * @return array{0: Product|null, 1: Variant|null}
     */
    private function matchBuyerItem(Tenant $tenant, ?PunchoutBuyer $buyer, string $code): array
    {
        if ($buyer?->buyer_party_id === null) {
            return [null, null];
        }

        $item = BuyerItem::query()
            ->where('buyer_party_id', $buyer->buyer_party_id)
            ->where('code_key', BuyerItem::keyFor($code))
            ->first();

        if ($item === null) {
            return [null, null];
        }

        $mapping = BuyerItemMapping::query()
            ->with(['product', 'variant'])
            ->where('tenant_id', $tenant->id)
            ->where('buyer_item_id', $item->id)
            ->whereHas('product', fn ($query) => $query
                ->where('tenant_id', $tenant->id)
                ->where('is_active', true)
                ->where('status', 'active')
                ->forChannel(SalesChannel::PUNCHOUT))
            ->orderByDesc('is_active')
            ->orderByDesc('is_primary')
            ->first();

        if ($mapping === null || $mapping->product === null) {
            return [null, null];
        }

        return [$mapping->product, $mapping->variant];
    }

    /**
     * @return array{0: Product|null, 1: Variant|null}
     */
    private function matchSupplierCode(Tenant $tenant, string $code): array
    {
        $variant = Variant::query()
            ->where('sku', $code)
            ->whereHas('product', fn ($query) => $query
                ->where('tenant_id', $tenant->id)
                ->where('is_active', true)
                ->where('status', 'active')
                ->forChannel(SalesChannel::PUNCHOUT))
            ->first();

        if ($variant?->product) {
            return [$variant->product, $variant];
        }

        $product = Product::query()
            ->where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->where('status', 'active')
            ->forChannel(SalesChannel::PUNCHOUT)
            ->where(fn ($query) => $query
                ->where('sku', $code)
                ->orWhere('uuid', $code)
                ->orWhere('part_number', $code))
            ->first();

        return [$product, null];
    }

    /**
     * Transitional: codes that still live only on price rows.
     *
     * @return array{0: Product|null, 1: Variant|null}
     */
    private function matchLegacyPrice(Tenant $tenant, string $code): array
    {
        $price = Price::query()
            ->with('priceable')
            ->where('tenant_id', $tenant->id)
            ->where('metadata->customer_part_number', $code)
            ->where('is_active', true)
            ->first();

        $priceable = $price?->priceable;

        if ($priceable instanceof Variant && $priceable->product) {
            return [$priceable->product, $priceable];
        }

        if ($priceable instanceof Product) {
            return [$priceable, null];
        }

        return [null, null];
    }

    private function renderResponse(
        Tenant $tenant,
        PunchoutInboundOrder $inbound,
        ?Order $order,
        bool $ignored = false,
    ): PunchoutResponseData {
        $payloadId = $tenant->uuid.'-order-'.$inbound->id;
        $statusCode = $ignored ? 202 : 200;
        $statusText = $ignored ? 'Accepted' : 'OK';
        $message = $ignored
            ? 'Recorded; HTAShop is not the seller of record for this buyer.'
            : 'Order accepted.';

        $orderResponse = $order !== null
            ? '    <OrderResponse><OrderReference orderID="'.e((string) $order->order_number).'"/></OrderResponse>'."\n"
            : '';

        $content = '<?xml version="1.0" encoding="UTF-8"?>'."\n"
            .'<cXML payloadID="'.e($payloadId).'" timestamp="'.now()->toIso8601String().'">'."\n"
            .'  <Response>'."\n"
            .'    <Status code="'.$statusCode.'" text="'.$statusText.'">'.e($message).'</Status>'."\n"
            .$orderResponse
            .'  </Response>'."\n"
            .'</cXML>';

        return new PunchoutResponseData($content, 'text/xml', $statusCode);
    }
}
