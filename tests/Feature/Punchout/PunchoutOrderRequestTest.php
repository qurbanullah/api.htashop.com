<?php

namespace Tests\Feature\Punchout;

use App\Models\Order;
use App\Models\PunchoutInboundOrder;
use App\Models\Tenant;
use Illuminate\Support\Str;

function punchoutOrderXml(
    string $payloadId = 'PO-1',
    string $part = 'SKU-ORD-1',
    float $quantity = 3,
    float $unitPrice = 12.00,
    string $currency = 'USD',
    array $credentials = [],
): string {
    $sender = $credentials['sender'] ?? 'buyer-sender';
    $secret = $credentials['secret'] ?? 'buyer-shared-secret';
    $from = $credentials['from'] ?? 'buyer-from';
    $to = $credentials['to'] ?? 'htashop';
    $total = number_format($quantity * $unitPrice, 2, '.', '');
    $unit = number_format($unitPrice, 2, '.', '');

    return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<cXML payloadID="{$payloadId}" timestamp="2026-10-23T10:00:00+00:00">
  <Header>
    <From><Credential><Identity>{$from}</Identity></Credential></From>
    <To><Credential><Identity>{$to}</Identity></Credential></To>
    <Sender><Credential><Identity>{$sender}</Identity><SharedSecret>{$secret}</SharedSecret></Credential></Sender>
  </Header>
  <Request>
    <OrderRequest>
      <OrderRequestHeader orderID="BUYER-{$payloadId}" orderDate="2026-10-23" type="new">
        <Total><Money currency="{$currency}">{$total}</Money></Total>
        <ShipTo>
          <Address>
            <Name>Buyer Co</Name>
            <Street>1 Main Street</Street>
            <City>Lahore</City>
            <PostalCode>54000</PostalCode>
            <Country isoCountryCode="PK">Pakistan</Country>
          </Address>
          <Email>buyer@example.com</Email>
        </ShipTo>
      </OrderRequestHeader>
      <ItemOut quantity="{$quantity}" lineNumber="1">
        <ItemID><SupplierPartID>{$part}</SupplierPartID></ItemID>
        <ItemDetail>
          <UnitPrice><Money currency="{$currency}">{$unit}</Money></UnitPrice>
          <Description>Order Widget</Description>
          <UnitOfMeasure>EA</UnitOfMeasure>
        </ItemDetail>
      </ItemOut>
    </OrderRequest>
  </Request>
</cXML>
XML;
}

function punchoutInboundTenant(string $slug, bool $enabled = true): Tenant
{
    return Tenant::create([
        'name' => Str::headline($slug),
        'slug' => $slug,
        'is_active' => true,
        'settings' => [
            'punchout' => [
                'inbound_orders_enabled' => $enabled,
            ],
        ],
    ]);
}

it('creates an order exactly once for a repeated cXML payload id', function () {
    $tenant = punchoutInboundTenant('inbound-tenant');
    punchoutBuyer($tenant);

    $vendor = punchoutOrganization($tenant, 'inbound-vendor');
    $product = punchoutProduct($tenant, $vendor, 'Order Widget', ['punchout'], ['price' => 12.00]);
    $product->update(['sku' => 'SKU-ORD-1']);

    $response = $this->call(
        'POST',
        '/api/v1/punchout/'.$tenant->uuid.'/order-request?protocol=cxml',
        [],
        [],
        [],
        ['CONTENT_TYPE' => 'text/xml'],
        punchoutOrderXml(),
    );

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('text/xml');
    $response->assertSee('OrderReference', false);

    expect(Order::query()->count())->toBe(1);

    $order = Order::query()->with('items')->firstOrFail();
    expect($order->source)->toBe('punchout');
    expect((float) $order->subtotal)->toBe(36.00);
    expect($order->items)->toHaveCount(1);
    expect((float) $order->items->first()->quantity)->toBe(3.0);

    $inbound = PunchoutInboundOrder::query()->firstOrFail();
    expect($inbound->payload_id)->toBe('PO-1');
    expect($inbound->status)->toBe('created');
    expect($inbound->order_id)->toBe($order->id);

    // Replaying the buyer's payloadID must not create a second order.
    $this->call(
        'POST',
        '/api/v1/punchout/'.$tenant->uuid.'/order-request?protocol=cxml',
        [],
        [],
        [],
        ['CONTENT_TYPE' => 'text/xml'],
        punchoutOrderXml(),
    )
        ->assertOk()
        ->assertSee('OrderReference', false);

    expect(Order::query()->count())->toBe(1);
    expect(PunchoutInboundOrder::query()->count())->toBe(1);
});

it('records but does not fulfil an inbound order when HTAShop is not the seller of record', function () {
    $tenant = punchoutInboundTenant('inbound-disabled-tenant', false);
    punchoutBuyer($tenant);

    $this->call(
        'POST',
        '/api/v1/punchout/'.$tenant->uuid.'/order-request?protocol=cxml',
        [],
        [],
        [],
        ['CONTENT_TYPE' => 'text/xml'],
        punchoutOrderXml('PO-DISABLED'),
    )
        ->assertStatus(202);

    expect(Order::query()->count())->toBe(0);
    expect(PunchoutInboundOrder::query()->firstOrFail()->status)->toBe('ignored');
});

it('rejects an inbound order with an unknown part number', function () {
    $tenant = punchoutInboundTenant('inbound-unknown-part');
    punchoutBuyer($tenant);

    $vendor = punchoutOrganization($tenant, 'unknown-part-vendor');
    punchoutProduct($tenant, $vendor, 'Known Widget', ['punchout'], ['price' => 12.00]);

    $this->call(
        'POST',
        '/api/v1/punchout/'.$tenant->uuid.'/order-request?protocol=cxml',
        [],
        [],
        [],
        ['CONTENT_TYPE' => 'text/xml'],
        punchoutOrderXml('PO-UNKNOWN', 'DOES-NOT-EXIST'),
    )
        ->assertStatus(422)
        ->assertJsonPath('success', false);

    expect(Order::query()->count())->toBe(0);
    expect(PunchoutInboundOrder::query()->firstOrFail()->status)->toBe('failed');
});

it('rejects inbound orders with unknown buyer credentials', function () {
    $tenant = punchoutInboundTenant('inbound-bad-credentials');
    punchoutBuyer($tenant);

    $this->call(
        'POST',
        '/api/v1/punchout/'.$tenant->uuid.'/order-request?protocol=cxml',
        [],
        [],
        [],
        ['CONTENT_TYPE' => 'text/xml'],
        punchoutOrderXml('PO-BAD-CREDS', credentials: ['secret' => 'wrong-secret']),
    )
        ->assertStatus(422);

    expect(Order::query()->count())->toBe(0);
    expect(PunchoutInboundOrder::query()->count())->toBe(0);
});

it('rejects a cxml order request without a payload id', function () {
    $tenant = punchoutInboundTenant('inbound-no-payload');

    $this->call(
        'POST',
        '/api/v1/punchout/'.$tenant->uuid.'/order-request?protocol=cxml',
        [],
        [],
        [],
        ['CONTENT_TYPE' => 'text/xml'],
        str_replace('payloadID="PO-1"', '', punchoutOrderXml()),
    )
        ->assertStatus(422);
});
