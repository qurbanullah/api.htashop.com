<?php

namespace Database\Seeders;

use App\Models\Manufacturer;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ManufacturerSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->manufacturers() as $m) {
            Manufacturer::updateOrCreate(
                ['slug' => Str::slug($m['name'])],
                [
                    'name' => $m['name'],
                    'code' => $m['code'],
                    'type' => $m['type'],
                    'country' => $m['country'],
                    'is_active' => true,
                ],
            );
        }

        $this->command->info('Manufacturers (OEM/Manufacturer) seeded.');
    }

    private function manufacturers(): array
    {
        return [
            ['name' => 'Foxconn', 'code' => 'FXCN', 'type' => 'oem', 'country' => 'Taiwan'],
            ['name' => 'Pegatron', 'code' => 'PEG', 'type' => 'oem', 'country' => 'Taiwan'],
            ['name' => 'Quanta Computer', 'code' => 'QUTA', 'type' => 'oem', 'country' => 'Taiwan'],
            ['name' => 'Wistron', 'code' => 'WST', 'type' => 'oem', 'country' => 'Taiwan'],
            ['name' => 'Flex', 'code' => 'FLEX', 'type' => 'oem', 'country' => 'Singapore'],
            ['name' => 'TSMC', 'code' => 'TSMC', 'type' => 'manufacturer', 'country' => 'Taiwan'],
            ['name' => 'Samsung', 'code' => 'SAMS', 'type' => 'manufacturer', 'country' => 'South Korea'],
            ['name' => 'LG', 'code' => 'LG', 'type' => 'manufacturer', 'country' => 'South Korea'],
            ['name' => 'Sony', 'code' => 'SONY', 'type' => 'manufacturer', 'country' => 'Japan'],
            ['name' => 'Bosch', 'code' => 'BSH', 'type' => 'manufacturer', 'country' => 'Germany'],
            ['name' => 'Siemens', 'code' => 'SIEM', 'type' => 'manufacturer', 'country' => 'Germany'],
            ['name' => 'Schneider Electric', 'code' => 'SCHN', 'type' => 'manufacturer', 'country' => 'France'],
            ['name' => 'Emerson', 'code' => 'EMR', 'type' => 'manufacturer', 'country' => 'USA'],
            ['name' => '3M', 'code' => '3M', 'type' => 'manufacturer', 'country' => 'USA'],
            ['name' => 'Honeywell', 'code' => 'HON', 'type' => 'manufacturer', 'country' => 'USA'],
            ['name' => 'ABB', 'code' => 'ABB', 'type' => 'manufacturer', 'country' => 'Switzerland'],
            ['name' => 'Panasonic', 'code' => 'PANA', 'type' => 'manufacturer', 'country' => 'Japan'],
            ['name' => 'Philips', 'code' => 'PHIL', 'type' => 'manufacturer', 'country' => 'Netherlands'],
            ['name' => 'Toshiba', 'code' => 'TOS', 'type' => 'manufacturer', 'country' => 'Japan'],
        ];
    }
}
