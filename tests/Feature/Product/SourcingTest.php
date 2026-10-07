<?php

use App\Enums\Sourcing;
use App\Http\Resources\V1\Product\ProductResource;
use App\Models\Organization;
use App\Models\Product;
use App\Models\Tenant;
use Illuminate\Support\Str;

function sourcingTenant(): Tenant
{
    return Tenant::query()->create([
        'name' => 'HTAShop',
        'slug' => 'src-'.Str::lower(Str::random(8)),
        'is_active' => true,
    ]);
}

function sourcingProduct(Tenant $tenant, array $attributes = []): Product
{
    $organization = Organization::query()->create([
        'tenant_id' => $tenant->id,
        'name' => 'HTAShop Store',
        'slug' => 'src-org-'.Str::lower(Str::random(8)),
    ]);

    return Product::query()->create(array_merge([
        'tenant_id' => $tenant->id,
        'organization_id' => $organization->id,
        'name' => 'BLDC Motor',
        'slug' => 'src-prod-'.Str::lower(Str::random(8)),
        'status' => 'active',
        'is_active' => true,
    ], $attributes));
}

describe('the Sourcing enum', function () {
    it('lists the four sourcing modes', function () {
        expect(Sourcing::ALL)->toBe([
            Sourcing::IN_STOCK,
            Sourcing::ON_DEMAND,
            Sourcing::DROPSHIP,
            Sourcing::PREORDER,
        ]);
    });

    it('only treats in-stock as payable on delivery', function () {
        expect(Sourcing::requiresAdvancePayment(Sourcing::IN_STOCK))->toBeFalse()
            ->and(Sourcing::requiresAdvancePayment(Sourcing::ON_DEMAND))->toBeTrue()
            ->and(Sourcing::requiresAdvancePayment(Sourcing::DROPSHIP))->toBeTrue()
            ->and(Sourcing::requiresAdvancePayment(Sourcing::PREORDER))->toBeTrue();
    });
});

describe('Product sourcing', function () {
    it('defaults to in-stock sourcing', function () {
        $product = sourcingProduct(sourcingTenant());

        expect($product->sourcing)->toBe(Sourcing::IN_STOCK)
            ->and($product->requiresAdvancePayment())->toBeFalse()
            ->and($product->availabilityLabel())->toBe('In Stock');
    });

    it('persists import-on-demand sourcing and derives an availability label', function () {
        $product = sourcingProduct(sourcingTenant(), [
            'sourcing' => Sourcing::ON_DEMAND,
            'lead_time_days' => 18,
            'origin_country' => 'CN',
            'sourcing_url' => 'https://supplier.example/motors',
            'supplier_reference' => 'TM-2212',
        ]);

        expect($product->lead_time_days)->toBe(18)
            ->and($product->origin_country)->toBe('CN')
            ->and($product->supplier_reference)->toBe('TM-2212')
            ->and($product->requiresAdvancePayment())->toBeTrue()
            ->and($product->availabilityLabel())->toBe('Ships in 18 days');
    });

    it('labels pre-orders without a lead time', function () {
        $product = sourcingProduct(sourcingTenant(), ['sourcing' => Sourcing::PREORDER]);

        expect($product->availabilityLabel())->toBe('Pre-order');
    });

    it('exposes sourcing fields and availability through the resource', function () {
        $product = sourcingProduct(sourcingTenant(), [
            'sourcing' => Sourcing::ON_DEMAND,
            'lead_time_days' => 18,
            'origin_country' => 'CN',
            'supplier_reference' => 'TM-2212',
        ]);

        $data = (new ProductResource($product))->resolve();

        expect($data['sourcing'])->toBe(Sourcing::ON_DEMAND)
            ->and($data['lead_time_days'])->toBe(18)
            ->and($data['origin_country'])->toBe('CN')
            ->and($data['availability'])->toBe('Ships in 18 days');
    });
});
