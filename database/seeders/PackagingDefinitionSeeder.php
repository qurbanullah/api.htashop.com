<?php

namespace Database\Seeders;

use App\Models\Definition;
use App\Models\DefinitionTarget;
use App\Models\Option;
use App\Models\Tenant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Packaging enumerations that operations may need to edit without a deploy.
 *
 * Units of measure (weight, dimensions, packaging type) come from the
 * `measurements` / `units` tables; only the closed legal lists live here.
 */
class PackagingDefinitionSeeder extends Seeder
{
    public function run(): void
    {
        $tenants = Tenant::all();

        foreach ($tenants as $tenant) {
            foreach ($this->definitions() as $data) {
                $options = $data['options'] ?? [];
                unset($data['options']);

                $definition = Definition::query()->firstOrNew([
                    'tenant_id' => $tenant->id,
                    'code' => $data['code'],
                    'kind' => 'packaging',
                ]);

                if (! $definition->exists) {
                    $definition->uuid = (string) Str::uuid();
                }

                $definition->fill(array_merge($data, [
                    'kind' => 'packaging',
                    'is_active' => true,
                ]))->save();

                foreach (['product', 'variant'] as $target) {
                    DefinitionTarget::firstOrCreate([
                        'definition_id' => $definition->id,
                        'target_type' => $target,
                    ]);
                }

                foreach ($options as $option) {
                    Option::updateOrCreate(
                        ['definition_id' => $definition->id, 'code' => $option['code']],
                        [
                            'name' => $option['name'],
                            'sort_order' => $option['sort_order'] ?? 0,
                            'is_active' => true,
                        ]
                    );
                }
            }
        }

        $this->command?->info('Packaging definitions seeded.');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function definitions(): array
    {
        return [
            [
                'name' => 'Hazard Class',
                'code' => 'pkg_hazard_class',
                'value_type' => 'select',
                'group_name' => 'Packaging',
                'is_required' => false,
                'options' => [
                    ['code' => '1', 'name' => '1 — Explosives', 'sort_order' => 1],
                    ['code' => '2.1', 'name' => '2.1 — Flammable gas', 'sort_order' => 2],
                    ['code' => '2.2', 'name' => '2.2 — Non-flammable gas', 'sort_order' => 3],
                    ['code' => '3', 'name' => '3 — Flammable liquid', 'sort_order' => 4],
                    ['code' => '4.1', 'name' => '4.1 — Flammable solid', 'sort_order' => 5],
                    ['code' => '4.2', 'name' => '4.2 — Spontaneously combustible', 'sort_order' => 6],
                    ['code' => '4.3', 'name' => '4.3 — Dangerous when wet', 'sort_order' => 7],
                    ['code' => '5.1', 'name' => '5.1 — Oxidising substance', 'sort_order' => 8],
                    ['code' => '5.2', 'name' => '5.2 — Organic peroxide', 'sort_order' => 9],
                    ['code' => '6.1', 'name' => '6.1 — Toxic substance', 'sort_order' => 10],
                    ['code' => '8', 'name' => '8 — Corrosive', 'sort_order' => 11],
                    ['code' => '9', 'name' => '9 — Miscellaneous', 'sort_order' => 12],
                ],
            ],
            [
                'name' => 'Packing Group',
                'code' => 'pkg_packing_group',
                'value_type' => 'select',
                'group_name' => 'Packaging',
                'is_required' => false,
                'options' => [
                    ['code' => 'I', 'name' => 'I — Great danger', 'sort_order' => 1],
                    ['code' => 'II', 'name' => 'II — Medium danger', 'sort_order' => 2],
                    ['code' => 'III', 'name' => 'III — Minor danger', 'sort_order' => 3],
                ],
            ],
        ];
    }
}
