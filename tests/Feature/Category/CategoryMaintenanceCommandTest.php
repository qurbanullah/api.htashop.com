<?php

namespace Tests\Feature\Category;

use App\Models\Category;
use App\Models\Organization;
use App\Models\Product;
use App\Models\Tenant;

function stockCategory(Category $category): Product
{
    $tenant = Tenant::create([
        'name' => 'Command Test Tenant',
        'slug' => 'command-test-tenant',
        'is_active' => true,
    ]);

    $organization = Organization::create([
        'tenant_id' => $tenant->id,
        'name' => 'Command Test Org',
        'slug' => 'command-test-org',
        'type' => 'vendor',
        'is_active' => true,
    ]);

    $product = Product::create([
        'tenant_id' => $tenant->id,
        'organization_id' => $organization->id,
        'name' => 'Command Test Product',
        'slug' => 'command-test-product',
        'status' => 'active',
        'is_active' => true,
    ]);

    $category->products()->attach($product->id);

    return $product;
}

it('lists stale categories without deleting them', function () {
    $stale = Category::factory()->create([
        'name' => 'Legacy Leaf',
        'slug' => 'legacy-leaf',
        'is_active' => true,
    ]);

    $this->artisan('catalog:prune-categories')->assertSuccessful();

    expect(Category::find($stale->id))->not->toBeNull();
});

it('soft deletes stale categories when forced', function () {
    $stale = Category::factory()->create([
        'name' => 'Legacy Leaf',
        'slug' => 'legacy-leaf',
        'is_active' => true,
    ]);

    $this->artisan('catalog:prune-categories --force')->assertSuccessful();

    expect(Category::find($stale->id))->toBeNull()
        ->and(Category::withTrashed()->find($stale->id)?->trashed())->toBeTrue();
});

it('collapses a stale subtree bottom up', function () {
    $parent = Category::factory()->create([
        'name' => 'Legacy Parent',
        'slug' => 'legacy-parent',
        'is_active' => true,
    ]);

    $child = Category::factory()->create([
        'name' => 'Legacy Child',
        'slug' => 'legacy-child',
        'parent_id' => $parent->id,
        'is_active' => true,
    ]);

    $this->artisan('catalog:prune-categories --force')->assertSuccessful();

    expect(Category::find($parent->id))->toBeNull()
        ->and(Category::find($child->id))->toBeNull();
});

it('keeps stale categories that still have products', function () {
    $stale = Category::factory()->create([
        'name' => 'Legacy Stocked',
        'slug' => 'legacy-stocked',
        'is_active' => true,
    ]);

    stockCategory($stale);

    $this->artisan('catalog:prune-categories --force')->assertSuccessful();

    expect(Category::find($stale->id))->not->toBeNull();
});

it('deactivates empty leaf categories but keeps stocked ones', function () {
    $root = Category::factory()->create([
        'name' => 'Maintenance Root',
        'slug' => 'maintenance-root',
        'is_active' => true,
    ]);

    $empty = Category::factory()->create([
        'name' => 'Empty Leaf',
        'slug' => 'empty-leaf',
        'parent_id' => $root->id,
        'is_active' => true,
    ]);

    $stocked = Category::factory()->create([
        'name' => 'Stocked Leaf',
        'slug' => 'stocked-leaf',
        'parent_id' => $root->id,
        'is_active' => true,
    ]);

    stockCategory($stocked);

    $this->artisan('catalog:deactivate-empty-categories --force')->assertSuccessful();

    expect($empty->fresh()->is_active)->toBeFalse()
        ->and($stocked->fresh()->is_active)->toBeTrue()
        ->and($root->fresh()->is_active)->toBeTrue();
});

it('deactivates an empty root together with its empty children', function () {
    $root = Category::factory()->create([
        'name' => 'Empty Root',
        'slug' => 'empty-root',
        'is_active' => true,
    ]);

    $child = Category::factory()->create([
        'name' => 'Empty Child',
        'slug' => 'empty-child',
        'parent_id' => $root->id,
        'is_active' => true,
    ]);

    $this->artisan('catalog:deactivate-empty-categories --include-roots --force')->assertSuccessful();

    expect($root->fresh()->is_active)->toBeFalse()
        ->and($child->fresh()->is_active)->toBeFalse();
});

it('keeps ancestors of stocked leaves and hides empty siblings', function () {
    $root = Category::factory()->create([
        'name' => 'Stocked Root',
        'slug' => 'stocked-root',
        'is_active' => true,
    ]);

    $branch = Category::factory()->create([
        'name' => 'Stocked Branch',
        'slug' => 'stocked-branch',
        'parent_id' => $root->id,
        'is_active' => true,
    ]);

    $stocked = Category::factory()->create([
        'name' => 'Stocked Leaf',
        'slug' => 'stocked-leaf',
        'parent_id' => $branch->id,
        'is_active' => true,
    ]);

    $empty = Category::factory()->create([
        'name' => 'Empty Sibling',
        'slug' => 'empty-sibling',
        'parent_id' => $root->id,
        'is_active' => true,
    ]);

    stockCategory($stocked);

    $this->artisan('catalog:deactivate-empty-categories --include-roots --force')->assertSuccessful();

    expect($root->fresh()->is_active)->toBeTrue()
        ->and($branch->fresh()->is_active)->toBeTrue()
        ->and($stocked->fresh()->is_active)->toBeTrue()
        ->and($empty->fresh()->is_active)->toBeFalse();
});

it('activates stocked branches and their ancestors without reviving empty siblings', function () {
    $root = Category::factory()->create([
        'name' => 'Stocked Root',
        'slug' => 'stocked-root',
        'is_active' => false,
    ]);

    $branch = Category::factory()->create([
        'name' => 'Stocked Branch',
        'slug' => 'stocked-branch',
        'parent_id' => $root->id,
        'is_active' => false,
    ]);

    $stocked = Category::factory()->create([
        'name' => 'Stocked Leaf',
        'slug' => 'stocked-leaf',
        'parent_id' => $branch->id,
        'is_active' => false,
    ]);

    $empty = Category::factory()->create([
        'name' => 'Empty Sibling',
        'slug' => 'empty-sibling',
        'parent_id' => $root->id,
        'is_active' => false,
    ]);

    stockCategory($stocked);

    $this->artisan('catalog:activate-stocked-categories --force')->assertSuccessful();

    expect($root->fresh()->is_active)->toBeTrue()
        ->and($branch->fresh()->is_active)->toBeTrue()
        ->and($stocked->fresh()->is_active)->toBeTrue()
        ->and($empty->fresh()->is_active)->toBeFalse();
});

it('leaves roots alone unless explicitly included', function () {
    $root = Category::factory()->create([
        'name' => 'Empty Root',
        'slug' => 'empty-root',
        'is_active' => true,
    ]);

    $this->artisan('catalog:deactivate-empty-categories --force')->assertSuccessful();

    expect($root->fresh()->is_active)->toBeTrue();

    $this->artisan('catalog:deactivate-empty-categories --include-roots --force')->assertSuccessful();

    expect($root->fresh()->is_active)->toBeFalse();
});
