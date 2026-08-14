<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\Variant;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class VariantSeeder extends Seeder
{
    public function run(): void
    {
        $products = Product::all();

        if ($products->isEmpty()) {
            $this->command->warn('⚠️  No products found. Run ProductSeeder before VariantSeeder.');
            return;
        }

        $existingVariantCount = Variant::count();
        $desiredVariantCount = 1000;

        if ($existingVariantCount >= $desiredVariantCount) {
            $this->command->info("Found {$existingVariantCount} existing variants, skipping variant creation.");
            return;
        }

        $configurationOptions = [
            'color' => ['Red', 'Blue', 'Black', 'White', 'Silver', 'Graphite'],
            'size' => ['Small', 'Medium', 'Large', 'XL', 'XXL'],
            'bundle' => ['Basic', 'Standard', 'Professional', 'Enterprise'],
        ];
        $statuses = ['draft', 'active', 'archived'];
        $variantsToCreate = $desiredVariantCount - $existingVariantCount;
        $perProduct = max(1, (int) ceil($variantsToCreate / $products->count()));

        foreach ($products as $product) {
            for ($index = 1; $index <= $perProduct; $index++) {
                if ($variantsToCreate <= 0) {
                    break 2;
                }

                $variantName = sprintf(
                    '%s %s',
                    $product->name,
                    fake()->randomElement(['Core', 'Plus', 'Max', 'Pro', 'Lite', 'X'])
                );

                $slug = Str::slug("{$variantName}-{$index}");

                Variant::create([
                    'product_id' => $product->id,
                    'name' => $variantName,
                    'slug' => $slug,
                    'status' => fake()->randomElement($statuses),
                    'summary' => fake()->sentence(8),
                    'description' => fake()->paragraphs(2, true),
                    'configuration' => [
                        'color' => fake()->randomElement($configurationOptions['color']),
                        'size' => fake()->randomElement($configurationOptions['size']),
                        'bundle' => fake()->randomElement($configurationOptions['bundle']),
                    ],
                    'is_default' => $index === 1,
                    'is_active' => fake()->boolean(90),
                    'metadata' => [
                        'sku' => 'VAR-' . strtoupper(fake()->bothify('????-####')),
                        'weight' => fake()->randomFloat(2, 0.5, 12.5),
                        'dimensions' => sprintf('%dx%dx%d', fake()->numberBetween(10, 100), fake()->numberBetween(10, 120), fake()->numberBetween(1, 20)),
                    ],
                ]);

                $variantsToCreate--;
            }
        }

        $this->command->info('✅ Variant seeding complete. Total variants: ' . Variant::count());
    }
}
