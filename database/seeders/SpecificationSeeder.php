<?php

namespace Database\Seeders;

use App\Models\Specification;
use Illuminate\Database\Seeder;

class SpecificationSeeder extends Seeder
{
    public function run(): void
    {
        $specifications = [
            // Measurements Group
            [
                'name' => 'Collar Width',
                'slug' => 'collar-width',
                'type' => 'product',
                'group' => 'Measurements',
                'description' => 'Width of the collar',
                'sort_order' => 1,
            ],
            [
                'name' => 'Shoulder Width',
                'slug' => 'shoulder-width',
                'type' => 'product',
                'group' => 'Measurements',
                'description' => 'Width across shoulders',
                'sort_order' => 2,
            ],
            [
                'name' => 'Sleeve Length',
                'slug' => 'sleeve-length',
                'type' => 'product',
                'group' => 'Measurements',
                'description' => 'Length of the sleeves',
                'sort_order' => 3,
            ],
            [
                'name' => 'Chest Width',
                'slug' => 'chest-width',
                'type' => 'product',
                'group' => 'Measurements',
                'description' => 'Width across chest',
                'sort_order' => 4,
            ],
            [
                'name' => 'Waist Width',
                'slug' => 'waist-width',
                'type' => 'product',
                'group' => 'Measurements',
                'description' => 'Width at waist',
                'sort_order' => 5,
            ],
            [
                'name' => 'Hip Width',
                'slug' => 'hip-width',
                'type' => 'product',
                'group' => 'Measurements',
                'description' => 'Width at hips',
                'sort_order' => 6,
            ],
            [
                'name' => 'Length',
                'slug' => 'length',
                'type' => 'product',
                'group' => 'Measurements',
                'description' => 'Total length of garment',
                'sort_order' => 7,
            ],
            [
                'name' => 'Inseam',
                'slug' => 'inseam',
                'type' => 'product',
                'group' => 'Measurements',
                'description' => 'Inside leg measurement',
                'sort_order' => 8,
            ],

            // Materials Group
            [
                'name' => 'Material Composition',
                'slug' => 'material-composition',
                'type' => 'product',
                'group' => 'Materials',
                'description' => 'Fabric composition',
                'sort_order' => 10,
            ],
            [
                'name' => 'Fabric Weight',
                'slug' => 'fabric-weight',
                'type' => 'product',
                'group' => 'Materials',
                'description' => 'Weight of fabric',
                'sort_order' => 11,
            ],
            [
                'name' => 'Lining Material',
                'slug' => 'lining-material',
                'type' => 'product',
                'group' => 'Materials',
                'description' => 'Material used for lining',
                'sort_order' => 12,
            ],

            // Care Instructions Group
            [
                'name' => 'Washing Instructions',
                'slug' => 'washing-instructions',
                'type' => 'product',
                'group' => 'Care Instructions',
                'description' => 'How to wash the garment',
                'sort_order' => 20,
            ],
            [
                'name' => 'Drying Instructions',
                'slug' => 'drying-instructions',
                'type' => 'product',
                'group' => 'Care Instructions',
                'description' => 'How to dry the garment',
                'sort_order' => 21,
            ],
            [
                'name' => 'Ironing Instructions',
                'slug' => 'ironing-instructions',
                'type' => 'product',
                'group' => 'Care Instructions',
                'description' => 'Ironing temperature and method',
                'sort_order' => 22,
            ],

            // Product Details Group
            [
                'name' => 'Country of Origin',
                'slug' => 'country-of-origin',
                'type' => 'product',
                'group' => 'Product Details',
                'description' => 'Where the product is manufactured',
                'sort_order' => 30,
            ],
            [
                'name' => 'Fit Type',
                'slug' => 'fit-type',
                'type' => 'product',
                'group' => 'Product Details',
                'description' => 'Fit style (Slim, Regular, Relaxed)',
                'sort_order' => 31,
            ],
            [
                'name' => 'Closure Type',
                'slug' => 'closure-type',
                'type' => 'product',
                'group' => 'Product Details',
                'description' => 'Type of closure (Button, Zipper, etc.)',
                'sort_order' => 32,
            ],
            [
                'name' => 'Neck Style',
                'slug' => 'neck-style',
                'type' => 'product',
                'group' => 'Product Details',
                'description' => 'Style of neckline',
                'sort_order' => 33,
            ],
            [
                'name' => 'Sleeve Type',
                'slug' => 'sleeve-type',
                'type' => 'product',
                'group' => 'Product Details',
                'description' => 'Type of sleeves',
                'sort_order' => 34,
            ],
            [
                'name' => 'Pattern',
                'slug' => 'pattern',
                'type' => 'product',
                'group' => 'Product Details',
                'description' => 'Pattern or design',
                'sort_order' => 35,
            ],
        ];

        foreach ($specifications as $specification) {
            Specification::updateOrCreate(
                ['slug' => $specification['slug'], 'type' => $specification['type']],
                $specification
            );
        }

        $this->command->info('✅ Created ' . count($specifications) . ' specifications');
    }
}
