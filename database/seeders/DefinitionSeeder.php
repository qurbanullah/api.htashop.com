<?php

namespace Database\Seeders;

use App\Models\Definition;
use App\Models\DefinitionTarget;
use App\Models\Measurement;
use App\Models\Option;
use App\Models\Tenant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DefinitionSeeder extends Seeder
{
    public function run(): void
    {
        $tenants = Tenant::all();

        if ($tenants->isEmpty()) {
            $tenants = collect([Tenant::create([
                'uuid' => (string) \Illuminate\Support\Str::uuid(),
                'name' => 'Default Tenant', 'slug' => 'default',
                'domain' => 'localhost', 'is_active' => true, 'settings' => [],
            ])]);
        }

        foreach ($tenants as $tenant) {
            foreach ($this->definitions() as $defData) {
                $options = $defData['options'] ?? [];
                $measurementCode = $defData['measurement_code'] ?? null;
                unset($defData['options'], $defData['measurement_code']);

                // Resolve measurement_id from code
                if ($measurementCode) {
                    $measurement = Measurement::where('code', $measurementCode)->first();
                    if ($measurement) {
                        $defData['measurement_id'] = $measurement->id;
                    }
                }

                $definition = Definition::updateOrCreate(
                    ['tenant_id' => $tenant->id, 'code' => $defData['code'], 'kind' => $defData['kind']],
                    array_merge($defData, ['tenant_id' => $tenant->id, 'uuid' => (string) \Illuminate\Support\Str::uuid()])
                );

                // Create target
                DefinitionTarget::firstOrCreate(
                    ['definition_id' => $definition->id, 'target_type' => 'variant']
                );

                // Create options (keyed by name so re-runs update codes without duplicating)
                foreach ($options as $opt) {
                    Option::updateOrCreate(
                        ['definition_id' => $definition->id, 'name' => $opt['name']],
                        [
                            'code' => $opt['code'] ?? Str::slug($opt['name']),
                            'metadata' => $opt['metadata'] ?? null,
                            'is_active' => true,
                        ]
                    );
                }
            }
        }

        $this->command->info('Variant definitions seeded.');
    }

    private function definitions(): array
    {
        return [

            // ═══ COLOR ═══
            [
                'kind' => 'attribute', 'code' => 'color', 'name' => 'Color',
                'value_type' => 'select', 'display_type' => 'swatch', 'swatch_type' => 'color',
                'is_required' => true, 'is_filterable' => true, 'group_name' => 'Appearance',
                'options' => [
                    ['name' => 'Red',    'code' => 'RED', 'metadata' => ['hex' => '#EF4444']],
                    ['name' => 'Blue',   'code' => 'BLU', 'metadata' => ['hex' => '#3B82F6']],
                    ['name' => 'Green',  'code' => 'GRN', 'metadata' => ['hex' => '#22C55E']],
                    ['name' => 'Black',  'code' => 'BLK', 'metadata' => ['hex' => '#111827']],
                    ['name' => 'White',  'code' => 'WHT', 'metadata' => ['hex' => '#F9FAFB']],
                    ['name' => 'Gray',   'code' => 'GRY', 'metadata' => ['hex' => '#6B7280']],
                    ['name' => 'Yellow', 'code' => 'YLW', 'metadata' => ['hex' => '#EAB308']],
                    ['name' => 'Orange', 'code' => 'ORG', 'metadata' => ['hex' => '#F97316']],
                    ['name' => 'Purple', 'code' => 'PRP', 'metadata' => ['hex' => '#A855F7']],
                    ['name' => 'Pink',   'code' => 'PNK', 'metadata' => ['hex' => '#EC4899']],
                    ['name' => 'Silver', 'code' => 'SLV', 'metadata' => ['hex' => '#C0C0C0']],
                    ['name' => 'Gold',   'code' => 'GLD', 'metadata' => ['hex' => '#FFD700']],
                ],
            ],

            // ═══ SIZE (Apparel) ═══
            [
                'kind' => 'attribute', 'code' => 'size', 'name' => 'Size',
                'value_type' => 'select', 'display_type' => 'button',
                'is_required' => true, 'is_filterable' => true, 'group_name' => 'Dimensions',
                'options' => [
                    ['name' => 'XS', 'code' => 'XS'],
                    ['name' => 'Small', 'code' => 'SM'],
                    ['name' => 'Medium', 'code' => 'MD'],
                    ['name' => 'Large', 'code' => 'LG'],
                    ['name' => 'XL', 'code' => 'XL'],
                    ['name' => '2XL', 'code' => '2XL'],
                    ['name' => '3XL', 'code' => '3XL'],
                    ['name' => '4XL', 'code' => '4XL'],
                    ['name' => '5XL', 'code' => '5XL'],
                ],
            ],

            // ═══ SHOE SIZE ═══
            [
                'kind' => 'attribute', 'code' => 'shoe_size', 'name' => 'Shoe Size',
                'value_type' => 'select', 'display_type' => 'button',
                'is_required' => true, 'is_filterable' => true, 'group_name' => 'Dimensions',
                'options' => array_map(fn($n) => ['name' => (string)$n], range(5, 15)),
            ],

            // ═══ MATERIAL ═══
            [
                'kind' => 'attribute', 'code' => 'material', 'name' => 'Material',
                'value_type' => 'select', 'display_type' => 'dropdown',
                'is_required' => false, 'is_filterable' => true, 'group_name' => 'Composition',
                'options' => [
                    ['name' => 'Steel', 'code' => 'ST'],
                    ['name' => 'Stainless Steel', 'code' => 'SS'],
                    ['name' => 'Aluminum', 'code' => 'AL'],
                    ['name' => 'Copper', 'code' => 'CU'],
                    ['name' => 'Brass', 'code' => 'BR'],
                    ['name' => 'Plastic', 'code' => 'PL'],
                    ['name' => 'Nylon', 'code' => 'NY'],
                    ['name' => 'Rubber', 'code' => 'RB'],
                    ['name' => 'Silicone', 'code' => 'SI'],
                    ['name' => 'Wood', 'code' => 'WD'],
                    ['name' => 'Glass', 'code' => 'GL'],
                    ['name' => 'Ceramic', 'code' => 'CE'],
                    ['name' => 'Carbon Fiber', 'code' => 'CF'],
                    ['name' => 'Leather', 'code' => 'LE'],
                    ['name' => 'Cotton', 'code' => 'CO'],
                    ['name' => 'Polyester', 'code' => 'PE'],
                    ['name' => 'Wool', 'code' => 'WO'],
                ],
            ],

            // ═══ DIAMETER ═══
            [
                'kind' => 'attribute', 'code' => 'diameter', 'name' => 'Diameter',
                'value_type' => 'number', 'display_type' => 'input',
                'is_required' => false, 'is_filterable' => true, 'group_name' => 'Dimensions',
                'measurement_code' => 'length',
                'options' => [],
            ],

            // ═══ LENGTH ═══
            [
                'kind' => 'attribute', 'code' => 'length', 'name' => 'Length',
                'value_type' => 'number', 'display_type' => 'input',
                'is_required' => false, 'is_filterable' => true, 'group_name' => 'Dimensions',
                'measurement_code' => 'length',
                'options' => [],
            ],

            // ═══ GAUGE / THICKNESS ═══
            [
                'kind' => 'attribute', 'code' => 'gauge', 'name' => 'Gauge / Thickness',
                'value_type' => 'select', 'display_type' => 'button',
                'is_required' => false, 'is_filterable' => true, 'group_name' => 'Dimensions',
                'options' => array_map(fn($n) => ['name' => (string)$n . ' gauge'], [10,12,14,16,18,20,22,24,26,28,30]),
            ],

            // ═══ PIPE SIZE ═══
            [
                'kind' => 'attribute', 'code' => 'pipe_size', 'name' => 'Pipe Size',
                'value_type' => 'select', 'display_type' => 'button',
                'is_required' => false, 'is_filterable' => true, 'group_name' => 'Dimensions',
                'options' => [
                    ['name' => '1/8"',   'code' => 'F18'],
                    ['name' => '1/4"',   'code' => 'F14'],
                    ['name' => '3/8"',   'code' => 'F38'],
                    ['name' => '1/2"',   'code' => 'F12'],
                    ['name' => '3/4"',   'code' => 'F34'],
                    ['name' => '1"',     'code' => '1'],
                    ['name' => '1-1/4"', 'code' => '114'],
                    ['name' => '1-1/2"', 'code' => '112'],
                    ['name' => '2"',     'code' => '2'],
                    ['name' => '2-1/2"', 'code' => '212'],
                    ['name' => '3"',     'code' => '3'],
                    ['name' => '4"',     'code' => '4'],
                    ['name' => '6"',     'code' => '6'],
                    ['name' => '8"',     'code' => '8'],
                    ['name' => '10"',    'code' => '10'],
                    ['name' => '12"',    'code' => '12'],
                ],
            ],

            // ═══ PRESSURE RATING ═══
            [
                'kind' => 'attribute', 'code' => 'pressure_rating', 'name' => 'Pressure Rating',
                'value_type' => 'select', 'display_type' => 'dropdown',
                'is_required' => false, 'is_filterable' => true, 'group_name' => 'Performance',
                'options' => array_map(fn($p) => ['name' => $p], [
                    '150 PSI', '300 PSI', '600 PSI', '900 PSI', '1500 PSI', '2500 PSI',
                ]),
            ],

            // ═══ VOLTAGE ═══
            [
                'kind' => 'attribute', 'code' => 'voltage', 'name' => 'Voltage',
                'value_type' => 'select', 'display_type' => 'dropdown',
                'is_required' => false, 'is_filterable' => true, 'group_name' => 'Electrical',
                'options' => array_map(fn($v) => ['name' => $v], [
                    '12V', '24V', '48V', '110V', '120V', '220V', '240V', '380V', '480V',
                ]),
            ],

            // ═══ CAPACITY ═══
            [
                'kind' => 'attribute', 'code' => 'capacity', 'name' => 'Capacity',
                'value_type' => 'number', 'display_type' => 'input',
                'is_required' => false, 'is_filterable' => true, 'group_name' => 'Performance',
                'measurement_code' => 'volume',
                'options' => [],
            ],

            // ═══ FINISH ═══
            [
                'kind' => 'attribute', 'code' => 'finish', 'name' => 'Finish',
                'value_type' => 'select', 'display_type' => 'dropdown',
                'is_required' => false, 'is_filterable' => true, 'group_name' => 'Appearance',
                'options' => array_map(fn($f) => ['name' => $f], [
                    'Matte', 'Glossy', 'Satin', 'Brushed', 'Polished',
                    'Galvanized', 'Anodized', 'Powder Coated', 'Painted', 'Raw',
                ]),
            ],

            // ═══ WEIGHT ═══
            [
                'kind' => 'attribute', 'code' => 'weight', 'name' => 'Weight',
                'value_type' => 'number', 'display_type' => 'input',
                'is_required' => false, 'is_filterable' => true, 'group_name' => 'Dimensions',
                'measurement_code' => 'mass',
                'options' => [],
            ],

            // ═══ PACKAGING ═══
            [
                'kind' => 'attribute', 'code' => 'pack_size', 'name' => 'Pack Size',
                'value_type' => 'select', 'display_type' => 'button',
                'is_required' => false, 'is_filterable' => false, 'group_name' => 'Packaging',
                'options' => array_map(fn($n) => ['name' => 'Pack of ' . $n], [1,2,5,10,25,50,100,500,1000]),
            ],

            // ═══ STORAGE TYPE ═══
            [
                'kind' => 'attribute', 'code' => 'storage_type', 'name' => 'Storage Type',
                'value_type' => 'select', 'display_type' => 'button',
                'is_required' => false, 'is_filterable' => true, 'group_name' => 'IT / Electronics',
                'options' => [
                    ['name' => 'NVMe SSD', 'code' => 'NV'],
                    ['name' => 'SATA SSD', 'code' => 'SA'],
                    ['name' => 'M.2 SSD', 'code' => 'M2'],
                    ['name' => 'HDD', 'code' => 'HD'],
                    ['name' => 'SSHD', 'code' => 'SH'],
                    ['name' => 'eMMC', 'code' => 'EM'],
                    ['name' => 'UFS', 'code' => 'UF'],
                ],
            ],

            // ═══ STORAGE CAPACITY ═══
            [
                'kind' => 'attribute', 'code' => 'storage', 'name' => 'Storage Capacity',
                'value_type' => 'select', 'display_type' => 'button',
                'is_required' => false, 'is_filterable' => true, 'group_name' => 'IT / Electronics',
                'options' => array_map(fn($s) => ['name' => $s], [
                    '64 GB', '128 GB', '256 GB', '512 GB',
                    '1 TB', '2 TB', '4 TB', '8 TB', '16 TB', '32 TB',
                ]),
            ],

            // ═══ MEMORY (RAM) ═══
            [
                'kind' => 'attribute', 'code' => 'memory', 'name' => 'Memory (RAM)',
                'value_type' => 'select', 'display_type' => 'button',
                'is_required' => false, 'is_filterable' => true, 'group_name' => 'IT / Electronics',
                'options' => array_map(fn($m) => ['name' => $m], [
                    '4 GB', '8 GB', '16 GB', '32 GB', '64 GB', '128 GB', '256 GB', '512 GB',
                ]),
            ],

            // ═══ RAM TYPE ═══
            [
                'kind' => 'attribute', 'code' => 'ram_type', 'name' => 'RAM Type',
                'value_type' => 'select', 'display_type' => 'dropdown',
                'is_required' => false, 'is_filterable' => true, 'group_name' => 'IT / Electronics',
                'options' => array_map(fn($r) => ['name' => $r], [
                    'DDR3', 'DDR3L', 'DDR4', 'DDR5', 'LPDDR4', 'LPDDR5', 'LPDDR5X',
                ]),
            ],

            // ═══ PROCESSOR ═══
            [
                'kind' => 'attribute', 'code' => 'processor', 'name' => 'Processor',
                'value_type' => 'select', 'display_type' => 'dropdown',
                'is_required' => false, 'is_filterable' => true, 'group_name' => 'IT / Electronics',
                'options' => [
                    ['name' => 'Intel Core i3', 'code' => 'I3'],
                    ['name' => 'Intel Core i5', 'code' => 'I5'],
                    ['name' => 'Intel Core i7', 'code' => 'I7'],
                    ['name' => 'Intel Core i9', 'code' => 'I9'],
                    ['name' => 'Intel Xeon', 'code' => 'XN'],
                    ['name' => 'AMD Ryzen 3', 'code' => 'R3'],
                    ['name' => 'AMD Ryzen 5', 'code' => 'R5'],
                    ['name' => 'AMD Ryzen 7', 'code' => 'R7'],
                    ['name' => 'AMD Ryzen 9', 'code' => 'R9'],
                    ['name' => 'Apple M1', 'code' => 'M1'],
                    ['name' => 'Apple M2', 'code' => 'M2'],
                    ['name' => 'Apple M3', 'code' => 'M3'],
                    ['name' => 'Apple M4', 'code' => 'M4'],
                ],
            ],

            // ═══ GPU ═══
            [
                'kind' => 'attribute', 'code' => 'gpu', 'name' => 'Graphics',
                'value_type' => 'select', 'display_type' => 'dropdown',
                'is_required' => false, 'is_filterable' => true, 'group_name' => 'IT / Electronics',
                'options' => array_map(fn($g) => ['name' => $g], [
                    'Integrated', 'NVIDIA GeForce RTX 3050', 'NVIDIA GeForce RTX 3060',
                    'NVIDIA GeForce RTX 3070', 'NVIDIA GeForce RTX 4060', 'NVIDIA GeForce RTX 4070',
                    'NVIDIA GeForce RTX 4080', 'NVIDIA GeForce RTX 4090',
                    'AMD Radeon RX 7600', 'AMD Radeon RX 7700', 'AMD Radeon RX 7800',
                ]),
            ],

            // ═══ CONNECTIVITY ═══
            [
                'kind' => 'attribute', 'code' => 'connectivity', 'name' => 'Connectivity',
                'value_type' => 'select', 'display_type' => 'dropdown', 'is_multi' => true,
                'is_required' => false, 'is_filterable' => true, 'group_name' => 'IT / Electronics',
                'options' => array_map(fn($c) => ['name' => $c], [
                    'Wi-Fi 6', 'Wi-Fi 6E', 'Wi-Fi 7', 'Bluetooth 5.0', 'Bluetooth 5.3',
                    'USB-C', 'USB 3.0', 'Thunderbolt 4', 'HDMI', 'DisplayPort', 'Ethernet',
                ]),
            ],

            // ═══ SCREEN SIZE ═══
            [
                'kind' => 'attribute', 'code' => 'screen_size', 'name' => 'Screen Size',
                'value_type' => 'select', 'display_type' => 'button',
                'is_required' => false, 'is_filterable' => true, 'group_name' => 'IT / Electronics',
                'options' => array_map(fn($s) => ['name' => $s . '"'], [
                    13, 14, 15, 16, 17, 24, 27, 32, 34, 43, 49, 55, 65, 75, 85,
                ]),
            ],
        ];
    }
}
