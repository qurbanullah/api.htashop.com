<?php

namespace Database\Factories;

use App\Models\Catalog;
use App\Models\Tenant;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Catalog>
 */
class CatalogFactory extends Factory
{
    protected $model = Catalog::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $tenantId = fn () => Tenant::create([
            'name' => fake()->company(),
            'slug' => fake()->slug(),
            'is_active' => true,
        ])->id;

        $organizationId = fn (array $attributes) => Organization::create([
            'tenant_id' => $attributes['tenant_id'],
            'name' => fake()->company() . ' Org',
            'slug' => fake()->slug(),
            'type' => 'vendor',
            'is_active' => true,
        ])->id;

        return [
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $tenantId,
            'organization_id' => $organizationId,
            'name' => fake()->word() . ' Catalog',
            'slug' => fake()->slug(),
            'description' => fake()->sentence(),
            'is_active' => true,
            'starts_at' => now(),
            'ends_at' => now()->addYear(),
            'metadata' => ['version' => '1.0'],
        ];
    }
}
