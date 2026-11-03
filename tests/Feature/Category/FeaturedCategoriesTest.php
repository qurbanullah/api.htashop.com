<?php

namespace Tests\Feature\Category;

use App\Http\Middleware\ApiAuthenticate;
use App\Models\Category;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::findOrCreate('admin', 'api');
    $this->withoutMiddleware(ApiAuthenticate::class);
    Cache::flush();
    config(['catalog.top_nav.limit' => 6]);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');
});

it('lists root categories with their featured state', function () {
    $featured = Category::factory()->create([
        'name' => 'Electronics',
        'slug' => 'electronics',
        'is_active' => true,
        'is_featured' => true,
        'featured_rank' => 1,
    ]);

    $available = Category::factory()->create([
        'name' => 'Machinery',
        'slug' => 'machinery',
        'is_active' => true,
        'is_featured' => false,
    ]);

    Category::factory()->create([
        'name' => 'Laptops',
        'slug' => 'laptops',
        'is_active' => true,
        'is_featured' => true,
        'featured_rank' => 2,
        'parent_id' => $featured->id,
    ]);

    $response = $this->actingAs($this->admin, 'api')
        ->getJson('/api/v1/admin/categories/featured')
        ->assertOk()
        ->assertJsonPath('data.limit', 6)
        ->assertJsonPath('data.featured_count', 1);

    $names = collect($response->json('data.categories'))->pluck('name')->all();

    // Children are not eligible for the top nav.
    expect($names)->toBe([$featured->name, $available->name]);
});

it('replaces the featured selection and preserves the submitted order', function () {
    $electronics = Category::factory()->create([
        'name' => 'Electronics',
        'slug' => 'electronics',
        'is_active' => true,
        'is_featured' => true,
        'featured_rank' => 1,
    ]);

    $it = Category::factory()->create([
        'name' => 'Information Technology',
        'slug' => 'information-technology',
        'is_active' => true,
        'is_featured' => false,
    ]);

    $robotics = Category::factory()->create([
        'name' => 'Robotics',
        'slug' => 'robotics',
        'is_active' => true,
        'is_featured' => false,
    ]);

    $this->actingAs($this->admin, 'api')
        ->putJson('/api/v1/admin/categories/featured', [
            'category_ids' => [$it->id, $electronics->id],
        ])
        ->assertOk()
        ->assertJsonPath('data.featured_count', 2);

    expect($it->fresh()->is_featured)->toBeTrue()
        ->and($it->fresh()->featured_rank)->toBe(1)
        ->and($electronics->fresh()->is_featured)->toBeTrue()
        ->and($electronics->fresh()->featured_rank)->toBe(2)
        ->and($robotics->fresh()->is_featured)->toBeFalse()
        ->and($robotics->fresh()->featured_rank)->toBe(0);

    $this->getJson('/api/v1/catalog/top-nav')
        ->assertOk()
        ->assertJsonPath('data.0.name', 'Information Technology')
        ->assertJsonPath('data.1.name', 'Electronics');
});

it('drops categories that are removed from the selection', function () {
    $keep = Category::factory()->create([
        'name' => 'Keep',
        'slug' => 'keep',
        'is_featured' => true,
        'featured_rank' => 1,
    ]);

    $drop = Category::factory()->create([
        'name' => 'Drop',
        'slug' => 'drop',
        'is_featured' => true,
        'featured_rank' => 2,
    ]);

    $this->actingAs($this->admin, 'api')
        ->putJson('/api/v1/admin/categories/featured', [
            'category_ids' => [$keep->id],
        ])
        ->assertOk();

    expect($drop->fresh()->is_featured)->toBeFalse()
        ->and($drop->fresh()->featured_rank)->toBe(0);
});

it('rejects more categories than the configured limit', function () {
    $ids = Category::factory()->count(7)->create()->pluck('id')->all();

    $this->actingAs($this->admin, 'api')
        ->putJson('/api/v1/admin/categories/featured', ['category_ids' => $ids])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('category_ids');
});

it('rejects child categories', function () {
    $parent = Category::factory()->create(['name' => 'Parent', 'slug' => 'parent']);
    $child = Category::factory()->create([
        'name' => 'Child',
        'slug' => 'child',
        'parent_id' => $parent->id,
    ]);

    $this->actingAs($this->admin, 'api')
        ->putJson('/api/v1/admin/categories/featured', ['category_ids' => [$child->id]])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('category_ids');

    expect($child->fresh()->is_featured)->toBeFalse();
});
