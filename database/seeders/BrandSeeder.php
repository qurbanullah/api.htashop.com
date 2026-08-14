<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Manufacturer;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class BrandSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->brands() as $name => $manufacturerName) {
            $manufacturer = Manufacturer::where('slug', Str::slug($manufacturerName))->first();

            Brand::updateOrCreate(
                ['slug' => Str::slug($name)],
                [
                    'name' => $name,
                    'manufacturer_id' => $manufacturer?->id,
                    'is_active' => true,
                ],
            );
        }

        $this->command->info('Brands seeded.');
    }

    /**
     * brand name => manufacturer name it belongs to.
     */
    private function brands(): array
    {
        return [
            'Apple' => 'Foxconn',
            'Dell' => 'Quanta Computer',
            'HP' => 'Wistron',
            'Lenovo' => 'Wistron',
            'Asus' => 'Pegatron',
            'Acer' => 'Quanta Computer',
            'Samsung' => 'Samsung',
            'LG' => 'LG',
            'Sony' => 'Sony',
            'Bosch' => 'Bosch',
            'Siemens' => 'Siemens',
            'Schneider Electric' => 'Schneider Electric',
            'Emerson' => 'Emerson',
            '3M' => '3M',
            'Honeywell' => 'Honeywell',
            'ABB' => 'ABB',
            'Panasonic' => 'Panasonic',
            'Philips' => 'Philips',
            'Toshiba' => 'Toshiba',
        ];
    }
}
