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

    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');
});

it('lists every category with hierarchy and usage counts', function () {
    $parent = Category::factory()->create([
        'name' => 'Electronics',
        'slug' => 'electronics',
        'is_active' => true,
    ]);

    Category::factory()->create([
        'name' => 'Development Boards',
        'slug' => 'development-boards',
        'parent_id' => $parent->id,
        'is_active' => false,
    ]);

    $response = $this->actingAs($this->admin, 'api')
        ->getJson('/api/v1/admin/categories')
        ->assertOk()
        ->assertJsonPath('data.total', 2)
        ->assertJsonPath('data.root_count', 1)
        ->assertJsonPath('data.active_count', 1);

    $categories = collect($response->json('data.categories'));
    $root = $categories->firstWhere('slug', 'electronics');
    $child = $categories->firstWhere('slug', 'development-boards');

    expect($root['children_count'])->toBe(1)
        ->and($root['products_count'])->toBe(0)
        ->and($child['is_active'])->toBeFalse()
        ->and($child['parent_id'])->toBe($parent->id);
});

it('creates a root category and derives its path', function () {
    $response = $this->actingAs($this->admin, 'api')
        ->postJson('/api/v1/admin/categories', [
            'name' => 'Machinery',
            'code' => 'mc',
            'description' => 'Industrial equipment',
            'is_active' => true,
            'sort_order' => 4,
        ])
        ->assertCreated()
        ->assertJsonPath('data.slug', 'machinery')
        ->assertJsonPath('data.code', 'MC')
        ->assertJsonPath('data.level', 0);

    $id = $response->json('data.id');

    expect(Category::find($id)->path)->toBe('/'.$id.'/');
});

it('creates a child category under the selected parent', function () {
    $parent = Category::factory()->create(['name' => 'Electronics', 'slug' => 'electronics']);

    $response = $this->actingAs($this->admin, 'api')
        ->postJson('/api/v1/admin/categories', [
            'name' => 'Sensors',
            'parent_id' => $parent->id,
        ])
        ->assertCreated()
        ->assertJsonPath('data.parent_id', $parent->id)
        ->assertJsonPath('data.level', 1);

    $child = Category::find($response->json('data.id'));

    expect($child->path)->toBe('/'.$parent->id.'/'.$child->id.'/');
});

it('moves a subtree and keeps descendant levels and paths correct', function () {
    $source = Category::factory()->create(['name' => 'Source', 'slug' => 'source']);
    $target = Category::factory()->create(['name' => 'Target', 'slug' => 'target']);

    $child = Category::factory()->create([
        'name' => 'Child',
        'slug' => 'child',
        'parent_id' => $source->id,
    ]);

    $grandchild = Category::factory()->create([
        'name' => 'Grandchild',
        'slug' => 'grandchild',
        'parent_id' => $child->id,
    ]);

    $this->actingAs($this->admin, 'api')
        ->putJson('/api/v1/admin/categories/'.$child->id, [
            'parent_id' => $target->id,
        ])
        ->assertOk();

    $child->refresh();
    $grandchild->refresh();

    expect($child->level)->toBe(1)
        ->and($child->path)->toBe('/'.$target->id.'/'.$child->id.'/')
        ->and($grandchild->level)->toBe(2)
        ->and($grandchild->path)->toBe('/'.$target->id.'/'.$child->id.'/'.$grandchild->id.'/');
});

it('rejects moving a category underneath its own descendant', function () {
    $parent = Category::factory()->create(['name' => 'Parent', 'slug' => 'parent']);
    $child = Category::factory()->create([
        'name' => 'Child',
        'slug' => 'child',
        'parent_id' => $parent->id,
    ]);

    $this->actingAs($this->admin, 'api')
        ->putJson('/api/v1/admin/categories/'.$parent->id, [
            'parent_id' => $child->id,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('parent_id');

    expect($parent->fresh()->parent_id)->toBeNull();
});

it('updates a category and regenerates the slug when it is blank', function () {
    $category = Category::factory()->create(['name' => 'Old name', 'slug' => 'old-name']);

    $this->actingAs($this->admin, 'api')
        ->putJson('/api/v1/admin/categories/'.$category->id, [
            'name' => 'New Name',
            'slug' => '',
            'is_active' => false,
        ])
        ->assertOk()
        ->assertJsonPath('data.name', 'New Name')
        ->assertJsonPath('data.slug', 'new-name')
        ->assertJsonPath('data.is_active', false);
});

it('deletes a leaf category', function () {
    $category = Category::factory()->create(['name' => 'Temporary', 'slug' => 'temporary']);

    $this->actingAs($this->admin, 'api')
        ->deleteJson('/api/v1/admin/categories/'.$category->id)
        ->assertOk()
        ->assertJsonPath('message', 'Category deleted successfully');

    expect(Category::find($category->id))->toBeNull();
});

it('refuses to delete a category that still has children', function () {
    $parent = Category::factory()->create(['name' => 'Parent', 'slug' => 'parent']);
    Category::factory()->create([
        'name' => 'Child',
        'slug' => 'child',
        'parent_id' => $parent->id,
    ]);

    $this->actingAs($this->admin, 'api')
        ->deleteJson('/api/v1/admin/categories/'.$parent->id)
        ->assertUnprocessable();

    expect(Category::find($parent->id))->not->toBeNull();
});
