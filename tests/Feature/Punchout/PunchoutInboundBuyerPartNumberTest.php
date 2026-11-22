<?php

namespace Tests\Feature\Punchout;

use App\Models\Order;
use App\Models\Tenant;
use App\Services\Punchout\BuyerItemMappingService;
use Illuminate\Support\Str;

function inboundBpnTenant(string $slug): Tenant
{
    return Tenant::create([
        'name' => Str::headline($slug),
        'slug' => $slug,
        'is_active' => true,
        'settings' => [
            'punchout' => [
                'inbound_orders_enabled' => true,
            ],
        ],
    ]);
}

/**
 * @param  array{supplier?: string, buyer?: string, mpn?: string, quantity?: float, price?: float}  $line
 * @param  array{sender?: string, secret?: string, from?: string, to?: string}  $credentials
 */
function inboundBpnXml(array $line = [], array $credentials = [], string $payloadId = 'BPN-PO-1'): string
{
    $supplier = $line['supplier'] ?? '';
    $buyerPart = $line['buyer'] ?? '';
    $mpn = $line['mpn'] ?? '';
    $quantity = $line['quantity'] ?? 1;
    $unitPrice = $line['price'] ?? 10.0;

    $sender = $credentials['sender'] ?? 'buyer-sender';
    $secret = $credentials['secret'] ?? 'buyer-shared-secret';
    $from = $credentials['from'] ?? 'buyer-from';
    $to = $credentials['to'] ?? 'htashop';

    $unit = number_format($unitPrice, 2, '.', '');
    $total = number_format($quantity * $unitPrice, 2, '.', '');

    $itemId = '<ItemID>';
    if ($supplier !== '') {
        $itemId .= '<SupplierPartID>'.htmlspecialchars($supplier, ENT_XML1).'</SupplierPartID>';
    }
    if ($buyerPart !== '') {
        $itemId .= '<BuyerPartID>'.htmlspecialchars($buyerPart, ENT_XML1).'</BuyerPartID>';
    }
    $itemId .= '</ItemID>';

    $itemDetail = '<ItemDetail>'
        .'<UnitPrice><Money currency="USD">'.$unit.'</Money></UnitPrice>'
        .'<Description>Inbound Item</Description>'
        .'<UnitOfMeasure>EA</UnitOfMeasure>'
        .($mpn !== '' ? '<ManufacturerPartID>'.htmlspecialchars($mpn, ENT_XML1).'</ManufacturerPartID>' : '')
        .'</ItemDetail>';

    return '<?xml version="1.0" encoding="UTF-8"?>'
        .'<cXML payloadID="'.$payloadId.'" timestamp="2026-11-22T10:00:00+00:00">'
        .'<Header>'
        .'<From><Credential><Identity>'.$from.'</Identity></Credential></From>'
        .'<To><Credential><Identity>'.$to.'</Identity></Credential></To>'
        .'<Sender><Credential><Identity>'.$sender.'</Identity><SharedSecret>'.$secret.'</SharedSecret></Credential></Sender>'
        .'</Header>'
        .'<Request><OrderRequest>'
        .'<OrderRequestHeader orderID="BUYER-'.$payloadId.'" orderDate="2026-11-22" type="new">'
        .'<Total><Money currency="USD">'.$total.'</Money></Total>'
        .'<ShipTo><Address><Name>Buyer Co</Name><Country isoCountryCode="SA">Saudi Arabia</Country></Address></ShipTo>'
        .'</OrderRequestHeader>'
        .'<ItemOut quantity="'.$quantity.'" lineNumber="1">'.$itemId.$itemDetail.'</ItemOut>'
        .'</OrderRequest></Request></cXML>';
}

function inboundBpnPost($test, Tenant $tenant, string $xml)
{
    return $test->call(
        'POST',
        '/api/v1/punchout/'.$tenant->uuid.'/order-request?protocol=cxml',
        [],
        [],
        [],
        ['CONTENT_TYPE' => 'text/xml'],
        $xml,
    );
}

it('matches an inbound line by BuyerPartID through the item master', function () {
    $tenant = inboundBpnTenant('inbound-bpn-tenant');
    $buyer = punchoutBuyer($tenant);
    $organization = punchoutOrganization($tenant, 'inbound-bpn-org');
    $product = punchoutProduct($tenant, $organization, 'Inbound Buyer Item', ['punchout'], ['price' => 10.00]);

    app(BuyerItemMappingService::class)->map($buyer, [
        'buyer_part_number' => '1000048217',
        'product_id' => $product->id,
    ]);

    inboundBpnPost($this, $tenant, inboundBpnXml([
        'supplier' => 'UNKNOWN-SUPPLIER-CODE',
        'buyer' => '1000048217',
    ]))->assertOk();

    $order = Order::query()->with('items')->firstOrFail();

    expect($order->items)->toHaveCount(1);
    expect($order->items->first()->product_id)->toBe($product->id);
    expect(data_get($order->items->first()->metadata, 'matched_by'))->toBe('buyer_item');
    expect(data_get($order->items->first()->metadata, 'buyer_part_number'))->toBe('1000048217');
});

it('prefers BuyerPartID over a SupplierPartID that matches another product', function () {
    $tenant = inboundBpnTenant('inbound-bpn-precedence');
    $buyer = punchoutBuyer($tenant);
    $organization = punchoutOrganization($tenant, 'inbound-bpn-precedence-org');

    $supplierProduct = punchoutProduct($tenant, $organization, 'Supplier Match', ['punchout'], ['price' => 10.00]);
    $supplierProduct->update(['sku' => 'SUP-A']);

    $buyerProduct = punchoutProduct($tenant, $organization, 'Buyer Match', ['punchout'], ['price' => 10.00]);

    app(BuyerItemMappingService::class)->map($buyer, [
        'buyer_part_number' => '1000048217',
        'product_id' => $buyerProduct->id,
    ]);

    inboundBpnPost($this, $tenant, inboundBpnXml([
        'supplier' => 'SUP-A',
        'buyer' => '1000048217',
    ]))->assertOk();

    $order = Order::query()->with('items')->firstOrFail();

    expect($order->items->first()->product_id)->toBe($buyerProduct->id);
    expect(data_get($order->items->first()->metadata, 'matched_by'))->toBe('buyer_item');
});

it('matches an inbound line by ManufacturerPartID', function () {
    $tenant = inboundBpnTenant('inbound-bpn-mpn');
    punchoutBuyer($tenant);
    $organization = punchoutOrganization($tenant, 'inbound-bpn-mpn-org');
    $product = punchoutProduct($tenant, $organization, 'MPN Match', ['punchout'], ['price' => 10.00]);
    $product->update(['model_number' => 'MPN-77']);

    inboundBpnPost($this, $tenant, inboundBpnXml([
        'mpn' => 'MPN-77',
    ]))->assertOk();

    $order = Order::query()->with('items')->firstOrFail();

    expect($order->items->first()->product_id)->toBe($product->id);
    expect(data_get($order->items->first()->metadata, 'matched_by'))->toBe('manufacturer_part');
});

it('falls back to legacy price metadata for an inbound code', function () {
    $tenant = inboundBpnTenant('inbound-bpn-legacy');
    punchoutBuyer($tenant);
    $organization = punchoutOrganization($tenant, 'inbound-bpn-legacy-org');
    $product = punchoutProduct($tenant, $organization, 'Legacy Inbound', ['punchout'], ['price' => 10.00]);

    punchoutPrice($tenant, $organization, $product, [
        'base_price' => 10.00,
        'metadata' => ['customer_part_number' => 'LEGACY-IN-1'],
    ]);

    inboundBpnPost($this, $tenant, inboundBpnXml([
        'buyer' => 'LEGACY-IN-1',
    ]))->assertOk();

    $order = Order::query()->with('items')->firstOrFail();

    expect($order->items->first()->product_id)->toBe($product->id);
    expect(data_get($order->items->first()->metadata, 'matched_by'))->toBe('legacy_price');
});

it('does not match a code mapped by another buyer', function () {
    $tenant = inboundBpnTenant('inbound-bpn-isolation');
    $organization = punchoutOrganization($tenant, 'inbound-bpn-isolation-org');
    $product = punchoutProduct($tenant, $organization, 'Isolated Inbound', ['punchout'], ['price' => 10.00]);

    $owner = punchoutBuyer($tenant, ['code' => 'owner-buyer']);
    punchoutBuyer($tenant, [
        'code' => 'stranger-buyer',
        'sender_identity' => 'stranger-sender',
        'from_identity' => 'stranger-from',
    ]);

    app(BuyerItemMappingService::class)->map($owner, [
        'buyer_part_number' => 'PRIVATE-9',
        'product_id' => $product->id,
    ]);

    inboundBpnPost($this, $tenant, inboundBpnXml(
        ['buyer' => 'PRIVATE-9'],
        ['sender' => 'stranger-sender', 'from' => 'stranger-from'],
        'BPN-PO-ISOLATED',
    ))->assertStatus(422);

    expect(Order::query()->count())->toBe(0);
});
