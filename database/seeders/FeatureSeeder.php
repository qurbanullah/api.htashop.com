<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Feature;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class FeatureSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->features() as $name => $categoryCodes) {
            $feature = Feature::updateOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name, 'is_active' => true],
            );

            $categoryIds = Category::whereIn('code', $categoryCodes)->pluck('id');
            if ($categoryIds->isNotEmpty()) {
                $feature->categories()->syncWithoutDetaching($categoryIds->all());
            }
        }

        $this->command->info('Product features seeded with category relationships.');
    }

    /**
     * feature name => category codes it belongs to.
     * A feature may belong to many categories via the `categorizables` pivot.
     */
    private function features(): array
    {
        return [
            // IT & Electronics
            'Wireless' => ['IT'],
            'Bluetooth' => ['IT'],
            'USB-C' => ['IT'],
            'HDMI' => ['IT'],
            'Touchscreen' => ['IT'],
            'Backlit Keyboard' => ['IT'],
            'Rechargeable Battery' => ['IT'],
            'Noise Cancelling' => ['IT'],

            // Cross-category (durability / protection)
            'IP67' => ['IT', 'MR', 'EL', 'SP'],
            'Waterproof' => ['IT', 'MR', 'SP', 'EL'],
            'Dustproof' => ['IT', 'MR', 'SP'],
            'Shockproof' => ['IT', 'MR'],
            'Corrosion Resistant' => ['MR', 'PL', 'CL'],
            'Rust Resistant' => ['AF', 'GL'],
            'Weather Resistant' => ['BC', 'AF', 'GL'],
            'UV Resistant' => ['BC', 'SP'],
            'Fire Rated' => ['BC', 'SP'],
            'Heavy Duty' => ['FU', 'MR', 'AF'],

            // Office Supplies
            'Eco Friendly' => ['OS', 'ED', 'CJ', 'GL'],
            'Recyclable' => ['OS', 'PS', 'PP'],
            'BPA Free' => ['OS', 'FB'],
            'Refillable' => ['OS'],

            // Furniture
            'Ergonomic' => ['FU'],
            'Adjustable Height' => ['FU'],
            'Foldable' => ['FU', 'SR'],
            'Wall Mountable' => ['FU', 'EL'],

            // MRO & Industrial
            'High Temperature Resistant' => ['MR'],

            // Safety & PPE
            'Hypoallergenic' => ['SP', 'MD'],
            'Anti-Slip' => ['SP'],
            'Flame Resistant' => ['SP'],
            'Impact Resistant' => ['SP', 'MR'],

            // Cleaning & Janitorial
            'Biodegradable' => ['CJ', 'PS', 'CL'],
            'Non-Toxic' => ['CJ', 'ED'],
            'Disinfectant' => ['CJ'],
            'Fragrance Free' => ['CJ'],

            // Packaging & Shipping
            'Cushioned' => ['PS'],
            'Tamper Evident' => ['PS'],
            'Moisture Resistant' => ['PS'],
            'Recycled Content' => ['PS', 'OS'],

            // Lab & Scientific
            'Calibrated' => ['LB'],
            'Precision' => ['LB'],
            'Lab Grade' => ['LB'],
            'Aseptic' => ['LB', 'MD'],

            // Medical & Healthcare
            'Latex Free' => ['MD'],
            'Disposable' => ['MD'],
            'Reusable' => ['MD'],
            'Medical Grade' => ['MD'],
            'Sterile' => ['MD', 'LB'],

            // Food & Beverage
            'Organic' => ['FB'],
            'Fair Trade' => ['FB'],
            'Non-GMO' => ['FB'],
            'Gluten Free' => ['FB'],
            'Vegan' => ['FB'],

            // Electrical & Lighting
            'Energy Star' => ['EL'],
            'Dimmable' => ['EL'],
            'LED' => ['EL'],
            'Surge Protected' => ['EL'],

            // Plumbing & HVAC
            'Lead Free' => ['PL'],
            'Low Flow' => ['PL'],
            'High Pressure' => ['PL'],
            'Frost Resistant' => ['PL', 'BC'],

            // Building & Construction
            'Load Bearing' => ['BC'],
            'Insulated' => ['BC'],

            // Automotive & Fleet
            'All Weather' => ['AF'],
            'Heavy Duty Towing' => ['AF'],

            // Textiles & Apparel
            'Breathable' => ['TA'],
            'Stretchable' => ['TA'],
            'Wrinkle Resistant' => ['TA'],
            'One Size' => ['TA'],

            // Sports & Recreation
            'Portable' => ['SR'],
            'Durable' => ['SR'],
            'Lightweight' => ['SR'],
            'Water Repellent' => ['SR', 'TA'],

            // Educational Supplies
            'Child Safe' => ['ED'],
            'Easy Clean' => ['ED', 'CJ'],

            // Chemicals & Lubricants
            'High Viscosity' => ['CL'],
            'Low VOC' => ['CL'],
            'Fast Drying' => ['CL'],
            'Food Grade' => ['CL', 'FB'],

            // Printing & Promotional
            'Customizable' => ['PP'],
            'High Resolution' => ['PP'],
            'Full Color' => ['PP'],

            // Gardening & Landscaping
            'Drought Resistant' => ['GL'],
            'Pet Safe' => ['GL'],
        ];
    }
}
