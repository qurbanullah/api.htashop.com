<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategoryTopNavSeeder extends Seeder
{
    public function run(): void
    {
        $slugs = [
            'it-electronics',
            'office-supplies',
            'furniture',
            'mro-industrial',
            'safety-ppe',
            'cleaning-janitorial',
            'packaging-shipping',
            'lab-scientific',
        ];

        foreach ($slugs as $slug) {
            $category = Category::query()->where('slug', $slug)->first();

            if (! $category) {
                continue;
            }

            $metadata = $category->metadata ?? [];
            $metadata['show_top_category_nav'] = true;

            $category->update(['metadata' => $metadata]);
        }

        $this->command->info('Top navigation categories flagged.');
    }
}
