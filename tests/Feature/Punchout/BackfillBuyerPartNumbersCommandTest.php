<?php

namespace Tests\Feature\Punchout;

use App\Models\BuyerItem;
use App\Models\BuyerItemMapping;
use App\Models\Contract;
use App\Models\Organization;
use App\Models\Tenant;
use Illuminate\Support\Str;

function backfillContract(Tenant $tenant, Organization $vendor, Organization $buyer): Contract
{
    return Contract::query()->create([
        'tenant_id' => $tenant->id,
        'vendor_id' => $vendor->id,
        'buyer_id' => $buyer->id,
        'currency_id' => punchoutCurrency()->id,
        'name' => 'Backfill Contract',
        'code' => 'bf-'.Str::lower(Str::random(6)),
        'contract_type' => 'blanket_purchase_agreement',
        'status' => 'active',
    ]);
}

it('backfills contract-scoped price part numbers into buyer items', function () {
    $tenant = punchoutTenant('backfill-bpn-tenant');
    $vendor = punchoutOrganization($tenant, 'backfill-bpn-vendor');
    $buyerOrganization = punchoutBuyerOrganization($tenant, 'backfill-bpn-buyer-org');
    $contract = backfillContract($tenant, $vendor, $buyerOrganization);
    $buyer = punchoutBuyer($tenant, [
        'contract_id' => $contract->id,
        'organization_id' => $buyerOrganization->id,
    ]);
    $product = punchoutProduct($tenant, $vendor, 'Backfill Product', ['punchout']);

    punchoutPrice($tenant, $vendor, $product, [
        'contract_id' => $contract->id,
        'base_price' => 10.00,
        'metadata' => ['customer_part_number' => 'BF-1000048217'],
    ]);

    $this->artisan('punchout:backfill-buyer-part-numbers', ['--dry-run' => true])
        ->assertSuccessful();

    expect(BuyerItem::query()->count())->toBe(0);
    expect(BuyerItemMapping::query()->count())->toBe(0);

    $this->artisan('punchout:backfill-buyer-part-numbers')->assertSuccessful();

    $item = BuyerItem::query()->firstOrFail();
    $mapping = BuyerItemMapping::query()->firstOrFail();

    expect($item->code)->toBe('BF-1000048217');
    expect($item->buyer_party_id)->toBe($buyer->refresh()->buyer_party_id);
    expect($mapping->tenant_id)->toBe($tenant->id);
    expect($mapping->product_id)->toBe($product->id);
    expect($mapping->source)->toBe('backfill');

    // Idempotent: running again must not duplicate rows.
    $this->artisan('punchout:backfill-buyer-part-numbers')->assertSuccessful();

    expect(BuyerItem::query()->count())->toBe(1);
    expect(BuyerItemMapping::query()->count())->toBe(1);
});

it('reports global price rows as ambiguous and writes nothing', function () {
    $tenant = punchoutTenant('backfill-bpn-global');
    $vendor = punchoutOrganization($tenant, 'backfill-bpn-global-vendor');
    $product = punchoutProduct($tenant, $vendor, 'Global Price Product', ['punchout']);

    punchoutPrice($tenant, $vendor, $product, [
        'base_price' => 10.00,
        'metadata' => ['customer_part_number' => 'GLOBAL-1'],
    ]);

    $this->artisan('punchout:backfill-buyer-part-numbers')->assertSuccessful();

    expect(BuyerItem::query()->count())->toBe(0);
    expect(BuyerItemMapping::query()->count())->toBe(0);
});
