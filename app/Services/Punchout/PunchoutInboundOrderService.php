<?php

namespace App\Services\Punchout;

use App\Actions\Punchout\NormalizePunchoutOrderRequestAction;
use App\Actions\Punchout\ResolvePunchoutBuyerAction;
use App\Actions\Punchout\ResolvePunchoutPriceAction;
use App\Actions\Punchout\ResolvePunchoutTenantAction;
use App\Actions\Punchout\ValidatePunchoutSetupCredentialsAction;
use App\Enums\OrderStatus;
use App\Enums\SalesChannel;
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
            [$product, $variant] = $this->matchLine($tenant, $line);

            if (! $product) {
                throw ValidationException::withMessages([
                    'items' => ["No punchout product matches part '".($line['supplier_part_id'] ?: $line['customer_part_number'] ?: 'unknown')."'."],
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
                    'customer_part_number' => $line['customer_part_number'] ?: $pricing['customer_part_number'],
                    'unit_of_measure' => $line['unit_of_measure'] ?: $pricing['unit_of_measure'],
                    'line_number' => $line['line_number'],
                    'price_source' => $pricing['source'],
                    'declared_unit_price' => $line['unit_price'],
                ], fn ($value) => $value !== null),
            ]);
        }

        return $order->load('items');
    }

    /**
     * Match a buyer part number against the seller's SKU/UUID, then against
     * negotiated customer part numbers stored on price records.
     *
     * @param  array<string, mixed>  $line
     * @return array{0: Product|null, 1: Variant|null}
     */
    private function matchLine(Tenant $tenant, array $line): array
    {
        $partNumber = trim((string) ($line['supplier_part_id'] ?: $line['customer_part_number']));

        if ($partNumber === '') {
            return [null, null];
        }

        $variant = Variant::query()
            ->where('sku', $partNumber)
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
                ->where('sku', $partNumber)
                ->orWhere('uuid', $partNumber)
                ->orWhere('part_number', $partNumber))
            ->first();

        if ($product) {
            return [$product, null];
        }

        $price = Price::query()
            ->with('priceable')
            ->where('tenant_id', $tenant->id)
            ->where('metadata->customer_part_number', $partNumber)
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
