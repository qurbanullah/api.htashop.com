<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\Product;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $organizations = Organization::all();

        if ($organizations->isEmpty()) {
            $this->command->warn('⚠️  No organizations found. Run OrganizationSeeder before ProductSeeder.');
            return;
        }

        $existingProductCount = Product::count();
        $desiredProductCount = 100;

        if ($existingProductCount >= $desiredProductCount) {
            $this->command->info("Found {$existingProductCount} existing products, skipping product creation.");
            return;
        }

        $productCategories = ['Standard', 'Premium', 'Enterprise', 'Pro', 'Essential', 'Advanced'];
        $statuses = ['draft', 'active', 'archived'];

        $productsToCreate = $desiredProductCount - $existingProductCount;

        for ($i = 0; $i < $productsToCreate; $i++) {
            $organization = $organizations->random();
            $baseName = fake()->unique()->words(3, true);
            $productName = sprintf('%s %s', ucfirst($baseName), fake()->randomElement(['Suite', 'Platform', 'System', 'Module', 'Solution']));
            $slug = Str::slug($productName);

            Product::create([
                'tenant_id' => $organization->tenant_id,
                'organization_id' => $organization->id,
                'name' => $productName,
                'slug' => $slug . '-' . ($i + 1),
                'status' => fake()->randomElement($statuses),
                'summary' => fake()->sentence(10),
                'description' => fake()->paragraphs(3, true),
                'is_active' => fake()->boolean(90),
                'metadata' => [
                    'category' => fake()->randomElement($productCategories),
                    'sku' => 'PROD-' . strtoupper(fake()->bothify('????-####')),
                    'release_cycle' => fake()->randomElement(['monthly', 'quarterly', 'annual']),
                ],
            ]);
        }

        $this->command->info('✅ Product seeding complete. Total products: ' . Product::count());
    }
}
