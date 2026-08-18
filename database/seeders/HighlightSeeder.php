<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Highlight;
use Illuminate\Database\Seeder;

class HighlightSeeder extends Seeder
{
    public function run(): void
    {
        $highlights = [
            [
                'code' => 'ssd-high-speed-transfer',
                'label' => 'High Speed Transfer',
                'heading' => 'High Speed Transfer',
                'body' => 'Fast read and write speeds let you move high-resolution photos, videos, and large files quickly.',
                'sort_order' => 1,
                'categories' => ['computer-components', 'servers-storage'],
            ],
            [
                'code' => 'ssd-plug-and-play',
                'label' => 'Plug and Play',
                'heading' => 'Plug and Play',
                'body' => 'Works right out of the box with no software installation required.',
                'sort_order' => 2,
                'categories' => ['computer-components'],
            ],
            [
                'code' => 'ssd-portable-lightweight',
                'label' => 'Portable & Lightweight',
                'heading' => 'Portable & Lightweight',
                'body' => 'Compact and lightweight design that is easy to carry wherever you go.',
                'sort_order' => 3,
                'categories' => ['computer-components'],
            ],
            [
                'code' => 'ssd-universal-compatibility',
                'label' => 'Universal Compatibility',
                'heading' => 'Universal Compatibility',
                'body' => 'Compatible with computers, smartphones, tablets, consoles, and more.',
                'sort_order' => 4,
                'categories' => ['computer-components', 'servers-storage'],
            ],
            [
                'code' => 'ssd-durable-reliable',
                'label' => 'Durable & Reliable',
                'heading' => 'Durable & Reliable',
                'body' => 'Solid-state design with no moving parts for dependable everyday use.',
                'sort_order' => 5,
                'categories' => ['computer-components', 'servers-storage'],
            ],
            [
                'code' => 'ram-high-performance',
                'label' => 'High Performance',
                'heading' => 'High Performance',
                'body' => 'Delivers fast, responsive performance for demanding applications.',
                'sort_order' => 1,
                'categories' => ['computer-components'],
            ],
            [
                'code' => 'ram-low-latency',
                'label' => 'Low Latency',
                'heading' => 'Low Latency',
                'body' => 'Optimized timings for smoother multitasking and reduced lag.',
                'sort_order' => 2,
                'categories' => ['computer-components'],
            ],
        ];

        foreach ($highlights as $data) {
            $categories = $data['categories'] ?? [];
            unset($data['categories']);

            // Re-sync category links on every run so seeder edits and schema
            // changes never leave stale categorizable rows behind.
            $highlight = Highlight::updateOrCreate(['code' => $data['code']], $data);

            if (! empty($categories)) {
                $categoryIds = Category::query()
                    ->whereIn('slug', $categories)
                    ->pluck('id')
                    ->all();

                if (! empty($categoryIds)) {
                    $highlight->categories()->sync($categoryIds);
                }
            }
        }

        $this->command->info('Seeded highlights.');
    }
}
