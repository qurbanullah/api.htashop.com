<?php

namespace Tests\Feature\Category;

use App\Models\Category;
use Database\Seeders\CategorySeeder;

it('splits electronics from information technology', function () {
    $this->seed(CategorySeeder::class);

    $electronics = Category::query()->where('slug', 'electronics')->first();
    expect($electronics)->not->toBeNull()
        ->and($electronics->code)->toBe('EC')
        ->and($electronics->parent_id)->toBeNull()
        ->and($electronics->children()->where('slug', 'development-boards-kits')->exists())->toBeTrue()
        ->and($electronics->children()->where('slug', 'electronic-components')->exists())->toBeTrue()
        ->and($electronics->children()->where('slug', 'laptops-notebooks')->exists())->toBeFalse()
        ->and($electronics->children()->where('slug', 'cameras-imaging')->exists())->toBeFalse();

    $it = Category::query()->where('slug', 'information-technology')->first();
    expect($it)->not->toBeNull()
        ->and($it->code)->toBe('IT')
        ->and($it->parent_id)->toBeNull()
        ->and($it->children()->where('slug', 'laptops-notebooks')->exists())->toBeTrue()
        ->and($it->children()->where('slug', 'networking-equipment')->exists())->toBeTrue()
        ->and($it->children()->where('slug', 'cameras-imaging')->exists())->toBeTrue()
        ->and($it->children()->where('slug', 'development-boards-kits')->exists())->toBeFalse();

    $laptops = Category::query()->where('slug', 'laptops-notebooks')->first();
    expect($laptops)->not->toBeNull()
        ->and($laptops->level)->toBe(1)
        ->and($laptops->path)->toBe('/'.$it->id.'/'.$laptops->id.'/');
});

it('splits electronic components into industrial sub-branches', function () {
    $this->seed(CategorySeeder::class);

    $electronics = Category::query()->where('slug', 'electronics')->firstOrFail();
    $components = Category::query()->where('slug', 'electronic-components')->firstOrFail();

    expect($components->parent_id)->toBe($electronics->id)
        ->and($components->level)->toBe(1)
        ->and($components->children()->count())->toBe(4)
        ->and($components->path)->toBe('/'.$electronics->id.'/'.$components->id.'/');

    $semiconductors = Category::query()->where('slug', 'semiconductors')->firstOrFail();

    expect($semiconductors->parent_id)->toBe($components->id)
        ->and($semiconductors->level)->toBe(2)
        ->and($semiconductors->path)->toBe('/'.$electronics->id.'/'.$components->id.'/'.$semiconductors->id.'/');
});

it('seeds the technology categories that office and IT buyers expect', function () {
    $this->seed(CategorySeeder::class);

    $it = Category::query()->where('slug', 'information-technology')->firstOrFail();
    $children = $it->children()->pluck('slug')->all();

    expect($children)->toContain(
        'ink-toner-print-supplies',
        'security-surveillance',
        'projectors-av-equipment',
        'barcode-pos-retail',
        'server-racks-kvms',
        'docking-stations-hubs',
    );
});

it('keeps lubricants and material handling in their industrial homes', function () {
    $this->seed(CategorySeeder::class);

    $mro = Category::query()->where('slug', 'mro-industrial')->firstOrFail();
    $chemicals = Category::query()->where('slug', 'chemicals-lubricants')->firstOrFail();

    expect($mro->children()->where('slug', 'lubricants-greases')->exists())->toBeFalse()
        ->and($mro->children()->where('slug', 'lubrication-equipment')->exists())->toBeTrue()
        ->and($chemicals->children()->where('slug', 'lubricating-oils')->exists())->toBeTrue()
        ->and($chemicals->children()->where('slug', 'greases')->exists())->toBeTrue();

    $materialHandling = Category::query()->where('slug', 'material-handling')->firstOrFail();

    expect($materialHandling->parent_id)->toBe($mro->id)
        ->and($materialHandling->children()->count())->toBe(6)
        ->and($materialHandling->path)->toBe('/'.$mro->id.'/'.$materialHandling->id.'/');
});

it('keeps safety workwear and gas detection under safety', function () {
    $this->seed(CategorySeeder::class);

    $safety = Category::query()->where('slug', 'safety-ppe')->firstOrFail();

    expect($safety->children()->where('slug', 'hand-protection')->exists())->toBeTrue()
        ->and($safety->children()->where('slug', 'protective-clothing')->exists())->toBeTrue()
        ->and($safety->children()->where('slug', 'gas-detection-monitoring')->exists())->toBeTrue()
        ->and($safety->children()->where('slug', 'emergency-showers-eyewash')->exists())->toBeTrue()
        ->and(Category::query()->where('slug', 'safety-workwear')->exists())->toBeFalse();
});

it('seeds the robotics category tree', function () {
    $this->seed(CategorySeeder::class);

    $robotics = Category::query()->where('slug', 'robotics-automation')->firstOrFail();

    expect($robotics)->not->toBeNull()
        ->and($robotics->code)->toBe('RB')
        ->and($robotics->is_active)->toBeTrue()
        ->and($robotics->children()->count())->toBe(14)
        ->and($robotics->children()->where('slug', 'robot-arms-cobots')->exists())->toBeTrue()
        ->and($robotics->children()->where('slug', 'agv-amr-systems')->exists())->toBeTrue()
        ->and($robotics->children()->where('slug', 'machine-vision')->exists())->toBeTrue();

    $motors = Category::query()->where('slug', 'motors-actuators')->first();
    expect($motors)->not->toBeNull()
        ->and($motors->parent_id)->toBe($robotics->id);

    $drones = Category::query()->where('slug', 'drone-uav-parts')->first();
    expect($drones)->not->toBeNull()
        ->and($drones->parent_id)->toBe($robotics->id);

    $automation = Category::query()->where('slug', 'industrial-automation')->first();
    expect($automation)->not->toBeNull()
        ->and($automation->parent_id)->toBe($robotics->id)
        ->and($automation->children()->count())->toBe(2);
});

it('keeps every category level, path and code consistent', function () {
    $this->seed(CategorySeeder::class);

    $roots = Category::query()->whereNull('parent_id')->get();
    expect($roots)->toHaveCount(22);

    foreach ($roots as $root) {
        expect($root->level)->toBe(0)
            ->and($root->path)->toBe('/'.$root->id.'/')
            ->and($root->code)->not->toBeEmpty();
    }

    $categories = Category::query()->with('parent')->get();

    foreach ($categories as $category) {
        if ($category->parent_id === null) {
            continue;
        }

        expect($category->level)->toBe($category->parent->level + 1)
            ->and($category->path)->toBe($category->parent->path.$category->id.'/');
    }
});

it('does not seed duplicate category names or slugs', function () {
    $this->seed(CategorySeeder::class);

    $duplicateSlugs = Category::query()
        ->select('slug')
        ->groupBy('slug')
        ->havingRaw('COUNT(*) > 1')
        ->pluck('slug');

    $duplicateNames = Category::query()
        ->select('name')
        ->groupBy('name')
        ->havingRaw('COUNT(*) > 1')
        ->pluck('name');

    expect($duplicateSlugs)->toBeEmpty()
        ->and($duplicateNames)->toBeEmpty();
});

it('only assigns codes that are explicitly defined in the seeder', function () {
    $this->seed(CategorySeeder::class);

    $codedChildren = Category::query()
        ->whereNotNull('parent_id')
        ->whereNotNull('code')
        ->pluck('slug')
        ->all();

    expect($codedChildren)->toBe(['electronic-components']);
});

it('preserves an operator deactivation across reseeds', function () {
    $this->seed(CategorySeeder::class);

    $category = Category::query()->where('slug', 'office-machines')->firstOrFail();
    $category->update(['is_active' => false]);

    $this->seed(CategorySeeder::class);

    expect($category->fresh()->is_active)->toBeFalse();
});

it('restores a soft deleted seeded category', function () {
    $this->seed(CategorySeeder::class);

    $category = Category::query()->where('slug', 'office-machines')->firstOrFail();
    $category->delete();

    expect(Category::query()->where('slug', 'office-machines')->exists())->toBeFalse();

    $this->seed(CategorySeeder::class);

    expect(Category::query()->where('slug', 'office-machines')->exists())->toBeTrue();
});

it('is idempotent when seeded twice', function () {
    $this->seed(CategorySeeder::class);
    $firstCount = Category::query()->count();

    $this->seed(CategorySeeder::class);

    expect(Category::query()->count())->toBe($firstCount);
});

it('renames a legacy it-electronics root in place', function () {
    Category::query()->create([
        'name' => 'IT & Electronics',
        'slug' => 'it-electronics',
        'code' => 'IT',
        'is_active' => true,
    ]);

    $this->seed(CategorySeeder::class);

    expect(Category::query()->where('slug', 'it-electronics')->exists())->toBeFalse()
        ->and(Category::query()->where('slug', 'electronics')->value('name'))->toBe('Electronics')
        ->and(Category::query()->whereNull('parent_id')->where('code', 'IT')->value('slug'))
        ->toBe('information-technology');
});
