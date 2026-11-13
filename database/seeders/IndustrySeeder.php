<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Industry;
use App\Models\Manufacturer;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class IndustrySeeder extends Seeder
{
    public function run(): void
    {
        $this->seedIndustries();
        $this->seedCategoryIndustries();
        $this->seedManufacturerIndustries();
        $this->seedBrandIndustries();

        $this->command?->info(sprintf(
            'Industries seeded: %d (%d categories, %d manufacturers, %d brands classified).',
            Industry::query()->count(),
            Category::query()->has('industries')->count(),
            Manufacturer::query()->has('industries')->count(),
            Brand::query()->has('industries')->count(),
        ));
    }

    private function seedIndustries(): void
    {
        $names = require database_path('data/industries.php');
        $sortOrder = 0;

        foreach ($names as $name) {
            Industry::updateOrCreate(
                ['code' => $this->codeFor($name)],
                [
                    'name' => $name,
                    'sort_order' => $sortOrder += 10,
                    'is_active' => true,
                ],
            );
        }
    }

    /**
     * `Str::slug()` drops slashes entirely ("Airlines/Aviation" becomes
     * "airlinesaviation"), so treat them as word separators first.
     */
    private function codeFor(string $name): string
    {
        return Str::slug(str_replace('/', ' ', $name));
    }

    /**
     * Which industries each root category supplies products to.
     *
     * @return array<string, array<int, string>>
     */
    private function categoryMap(): array
    {
        return [
            'electronics' => ['consumer-electronics', 'semiconductors', 'electrical-electronic-manufacturing'],
            'information-technology' => ['computer-hardware', 'computer-networking', 'information-technology-services'],
            'office-supplies' => ['business-supplies-equipment'],
            'furniture' => ['furniture'],
            'mro-industrial' => ['industrial-automation', 'machinery'],
            'safety-ppe' => ['business-supplies-equipment'],
            'cleaning-janitorial' => ['facilities-services'],
            'packaging-shipping' => ['packaging-containers', 'logistics-supply-chain'],
            'lab-scientific' => ['research', 'biotechnology', 'pharmaceuticals'],
            'medical-healthcare' => ['medical-device', 'hospital-health-care'],
            'food-beverage' => ['food-beverages', 'restaurants'],
            'electrical-lighting' => ['electrical-electronic-manufacturing', 'utilities'],
            'plumbing-hvac' => ['construction', 'mechanical-or-industrial-engineering'],
            'building-construction' => ['building-materials', 'construction', 'civil-engineering'],
            'automotive-fleet' => ['automotive', 'transportation-trucking-railroad'],
            'textiles-apparel' => ['textiles', 'apparel-fashion'],
            'sports-recreation' => ['sporting-goods'],
            'educational-supplies' => ['primary-secondary', 'higher-education', 'e-learning'],
            'chemicals-lubricants' => ['chemicals', 'oil-energy'],
            'printing-promotional' => ['printing', 'marketing-advertising'],
            'gardening-landscaping' => ['farming', 'environmental-services'],
            'robotics-automation' => ['industrial-automation', 'machinery'],
        ];
    }

    private function seedCategoryIndustries(): void
    {
        foreach ($this->categoryMap() as $categorySlug => $industryCodes) {
            $category = Category::query()
                ->where('slug', $categorySlug)
                ->whereNull('parent_id')
                ->first();

            if ($category === null) {
                continue;
            }

            $industryIds = Industry::query()->whereIn('code', $industryCodes)->pluck('id');

            if ($industryIds->isNotEmpty()) {
                $category->industries()->syncWithoutDetaching($industryIds);
            }
        }
    }

    /**
     * Manufacturer slug groups keyed by industry code.
     *
     * @return array<string, array<int, string>>
     */
    private function manufacturerMap(): array
    {
        return [
            'electrical-electronic-manufacturing' => [
                'foxconn', 'pegatron', 'quanta-computer', 'wistron', 'compal-electronics', 'inventec',
                'flex', 'jabil', 'sanmina', 'celestica', 'benchmark-electronics', 'plexus',
                'lite-on-technology', 'chicony-electronics', 'tpv-technology', 'qisda', 'clevo',
                'mitac', 'amtran-technology', 'delta-electronics',
                'vicor', 'xp-power', 'traco-power', 'recom-power', 'cincon-electronics', 'mornsun',
            ],
            'semiconductors' => [
                'tsmc', 'sk-hynix', 'micron-technology', 'intel', 'amd', 'nvidia', 'qualcomm',
                'broadcom', 'mediatek', 'texas-instruments', 'stmicroelectronics', 'nxp-semiconductors',
                'infineon-technologies', 'renesas-electronics', 'microchip-technology', 'analog-devices',
                'onsemi', 'vishay-intertechnology', 'diodes-incorporated', 'murata-manufacturing', 'tdk',
                'yageo', 'kemet', 'nichicon', 'te-connectivity', 'amphenol', 'molex', 'jst',
                'hirose-electric', 'wurth-elektronik', 'mean-well', 'espressif-systems',
                'nordic-semiconductor', 'silicon-labs', 'u-blox', 'quectel', 'arduino', 'raspberry-pi',
            ],
            'industrial-automation' => [
                'siemens', 'schneider-electric', 'abb', 'rockwell-automation', 'mitsubishi-electric',
                'omron', 'bosch', 'bosch-rexroth', 'festo', 'smc-corporation', 'parker-hannifin',
                'eaton', 'emerson', 'honeywell', 'yokogawa', 'endress-hauser', 'pepperl-fuchs', 'sick',
                'keyence', 'balluff', 'turck', 'ifm-electronic', 'pilz', 'schmersal', 'leuze', 'wago',
                'beckhoff-automation', 'b-r-industrial-automation', 'carlo-gavazzi', 'finder',
                'legrand', 'hager', 'autonics', 'ls-electric', 'fatek', 'sensirion', 'basler', 'cognex',
                'fanuc', 'kuka', 'yaskawa', 'universal-robots',
            ],
            'consumer-electronics' => ['sony', 'panasonic', 'philips', 'toshiba', 'lg', 'samsung'],
            'chemicals' => ['3m'],
        ];
    }

    private function seedManufacturerIndustries(): void
    {
        foreach ($this->manufacturerMap() as $industryCode => $slugs) {
            $this->attachManufacturers($industryCode, $slugs);
        }

        // Every ISO 10261 record is an earth-moving / agricultural machine maker.
        $wmc = require database_path('data/wmc-manufacturers-2026.php');
        $this->attachManufacturers('machinery', array_map(
            fn (array $manufacturer): string => Str::slug($manufacturer['name']),
            $wmc,
        ));
    }

    /**
     * @param  array<int, string>  $slugs
     */
    private function attachManufacturers(string $industryCode, array $slugs): void
    {
        $industry = Industry::query()->where('code', $industryCode)->first();

        if ($industry === null) {
            return;
        }

        Manufacturer::query()
            ->whereIn('slug', $slugs)
            ->select('id')
            ->chunkById(200, function ($manufacturers) use ($industry): void {
                $industry->manufacturers()->syncWithoutDetaching($manufacturers->pluck('id'));
            });
    }

    /**
     * Brands inherit the classification of the manufacturer that makes them.
     */
    private function seedBrandIndustries(): void
    {
        Brand::query()
            ->whereNotNull('manufacturer_id')
            ->with('manufacturer.industries')
            ->chunkById(200, function ($brands): void {
                foreach ($brands as $brand) {
                    $industryIds = $brand->manufacturer?->industries->pluck('id') ?? collect();

                    if ($industryIds->isNotEmpty()) {
                        $brand->industries()->syncWithoutDetaching($industryIds);
                    }
                }
            });
    }
}
