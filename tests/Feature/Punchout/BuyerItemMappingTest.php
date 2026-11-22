<?php

namespace Tests\Feature\Punchout;

use App\Models\BuyerItem;
use App\Models\BuyerItemMapping;
use App\Services\Punchout\BuyerItemMappingService;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

function punchoutMapper(): BuyerItemMappingService
{
    return app(BuyerItemMappingService::class);
}

it('creates a buyer item and a tenant mapping', function () {
    $tenant = punchoutTenant('mapping-create-tenant');
    $organization = punchoutOrganization($tenant, 'mapping-create-org');
    $product = punchoutProduct($tenant, $organization, 'MPU6050 Module', ['punchout']);
    $buyer = punchoutBuyer($tenant);

    $result = punchoutMapper()->map($buyer, [
        'buyer_part_number' => '1000048217',
        'short_description' => 'MPU6050 MODULE',
        'long_description' => 'Six axis motion tracking module, MPU6050, I2C',
        'unit_of_measure' => 'EA',
        'manufacturer_part_number' => 'MPU-6050',
        'product_id' => $product->id,
    ]);

    expect($result['action'])->toBe('created');
    expect($result['created_item'])->toBeTrue();
    expect($result['item']->code)->toBe('1000048217');
    expect($result['item']->short_description)->toBe('MPU6050 MODULE');
    expect($result['item']->long_description)->toContain('I2C');
    expect($result['mapping']->tenant_id)->toBe($tenant->id);
    expect($result['mapping']->product_id)->toBe($product->id);
});

it('reuses the shared buyer item for another tenant and only adds a mapping', function () {
    $tenantA = punchoutTenant('mapping-share-alpha');
    $tenantB = punchoutTenant('mapping-share-beta');
    $orgA = punchoutOrganization($tenantA, 'mapping-share-org-a');
    $orgB = punchoutOrganization($tenantB, 'mapping-share-org-b');
    $productA = punchoutProduct($tenantA, $orgA, 'Shared Item A', ['punchout']);
    $productB = punchoutProduct($tenantB, $orgB, 'Shared Item B', ['punchout']);

    $buyerA = punchoutBuyer($tenantA, ['settings' => ['network_id' => 'AN-SHARED-1']]);
    $buyerB = punchoutBuyer($tenantB, ['settings' => ['network_id' => 'AN-SHARED-1']]);

    $first = punchoutMapper()->map($buyerA, [
        'buyer_part_number' => '1000048217',
        'short_description' => 'MPU6050 MODULE',
        'product_id' => $productA->id,
    ]);

    $second = punchoutMapper()->map($buyerB, [
        'buyer_part_number' => '1000048217',
        'product_id' => $productB->id,
    ]);

    expect($second['created_item'])->toBeFalse();
    expect($second['item']->id)->toBe($first['item']->id);
    expect(BuyerItem::query()->count())->toBe(1);
    expect(BuyerItemMapping::query()->count())->toBe(2);
    expect($second['mapping']->tenant_id)->toBe($tenantB->id);
});

it('treats a repeated mapping to the same product as unchanged', function () {
    $tenant = punchoutTenant('mapping-unchanged-tenant');
    $organization = punchoutOrganization($tenant, 'mapping-unchanged-org');
    $product = punchoutProduct($tenant, $organization, 'Unchanged Product', ['punchout']);
    $buyer = punchoutBuyer($tenant);

    punchoutMapper()->map($buyer, [
        'buyer_part_number' => '00012345',
        'product_id' => $product->id,
    ]);

    $result = punchoutMapper()->map($buyer, [
        'buyer_part_number' => ' 00012345 ',
        'product_id' => $product->id,
    ]);

    expect($result['action'])->toBe('unchanged');
    expect($result['item']->code)->toBe('00012345');
    expect($result['item']->code_key)->toBe('00012345');
    expect(BuyerItemMapping::query()->count())->toBe(1);
});

it('rejects a conflicting mapping unless the caller asks to remap', function () {
    $tenant = punchoutTenant('mapping-conflict-tenant');
    $organization = punchoutOrganization($tenant, 'mapping-conflict-org');
    $firstProduct = punchoutProduct($tenant, $organization, 'Conflict Product One', ['punchout']);
    $secondProduct = punchoutProduct($tenant, $organization, 'Conflict Product Two', ['punchout']);
    $buyer = punchoutBuyer($tenant);

    punchoutMapper()->map($buyer, [
        'buyer_part_number' => 'ABC-1',
        'product_id' => $firstProduct->id,
    ]);

    expect(fn () => punchoutMapper()->map($buyer, [
        'buyer_part_number' => 'abc-1',
        'product_id' => $secondProduct->id,
    ]))->toThrow(ConflictHttpException::class);

    $remapped = punchoutMapper()->map($buyer, [
        'buyer_part_number' => 'abc-1',
        'product_id' => $secondProduct->id,
        'remap' => true,
    ]);

    expect($remapped['action'])->toBe('remapped');
    expect($remapped['mapping']->product_id)->toBe($secondProduct->id);
    expect(BuyerItemMapping::query()->count())->toBe(1);
});

it('rejects a product from another tenant', function () {
    $tenant = punchoutTenant('mapping-tenant-scope');
    $otherTenant = punchoutTenant('mapping-tenant-other');
    $otherOrganization = punchoutOrganization($otherTenant, 'mapping-other-org');
    $otherProduct = punchoutProduct($otherTenant, $otherOrganization, 'Foreign Product', ['punchout']);
    $buyer = punchoutBuyer($tenant);

    expect(fn () => punchoutMapper()->map($buyer, [
        'buyer_part_number' => 'X-1',
        'product_id' => $otherProduct->id,
    ]))->toThrow(ValidationException::class);
});
