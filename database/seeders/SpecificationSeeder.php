<?php

namespace Database\Seeders;

use App\Models\Specification;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class SpecificationSeeder extends Seeder
{
    public function run(): void
    {
        $catalog = $this->catalog();
        $sortOrder = 0;
        $count = 0;

        foreach ($catalog as $group => $specifications) {
            $sortOrder += 10;

            foreach ($specifications as $specification) {
                [$name, $unit, $description] = $specification + [null, null];

                Specification::updateOrCreate(
                    ['slug' => Str::slug($name), 'type' => 'product'],
                    [
                        'name' => $name,
                        'group' => $group,
                        'unit' => $unit,
                        'description' => $description,
                        'sort_order' => $sortOrder++,
                    ]
                );

                $count++;
            }
        }

        $this->command?->info(sprintf(
            'Seeded %d product specifications across %d groups.',
            $count,
            count($catalog),
        ));
    }

    /**
     * Specification key catalogue grouped for the product editor dropdown.
     *
     * Each entry is `[name, suggested unit code, description]`; the unit code
     * matches the `units` table and is only a default the editor can change.
     *
     * @return array<string, array<int, array{0: string, 1?: string|null, 2?: string|null}>>
     */
    private function catalog(): array
    {
        return [
            'Dimensions' => [
                ['Length', 'mm', 'Overall length end to end'],
                ['Width', 'mm', 'Overall width'],
                ['Height', 'mm', 'Overall height'],
                ['Depth', 'mm', 'Front-to-back depth'],
                ['Thickness', 'mm', 'Wall or panel thickness'],
                ['Diameter', 'mm', 'Nominal diameter'],
                ['Inner Diameter', 'mm', 'Inside diameter'],
                ['Outer Diameter', 'mm', 'Outside diameter'],
                ['Wall Thickness', 'mm', 'Pipe or tube wall thickness'],
                ['Pin Pitch', 'mm', 'Distance between component pins'],
                ['Lead Length', 'mm', 'Component lead length'],
                ['Wire Gauge', 'gauge', 'Conductor size (AWG)'],
                ['Cable Length', 'm', 'Supplied cable length'],
                ['Screen Size', 'in', 'Diagonal display size'],
                ['Screen Resolution', 'px', 'Native horizontal resolution'],
                ['Display Area', 'mm2', 'Active display area'],
            ],

            'Weight & Capacity' => [
                ['Weight', 'kg', 'Product weight'],
                ['Net Weight', 'kg', 'Weight excluding packaging'],
                ['Gross Weight', 'kg', 'Weight including packaging'],
                ['Load Capacity', 'kg', 'Maximum supported load'],
                ['Lifting Capacity', 'kg', 'Maximum lifting capacity'],
                ['Volume', 'L', 'Nominal volume'],
                ['Tank Capacity', 'L', 'Fluid tank capacity'],
                ['Flow Rate', 'L_min', 'Volumetric flow rate'],
                ['Storage Capacity', 'GB', 'Onboard storage'],
                ['Memory', 'GB', 'Installed memory'],
                ['Battery Capacity', 'mah', 'Battery charge capacity'],
                ['Package Quantity', 'pc', 'Units per package'],
            ],

            'Electrical' => [
                ['Operating Voltage', 'v', 'Nominal operating voltage'],
                ['Input Voltage', 'v', 'Accepted input voltage range'],
                ['Output Voltage', 'v', 'Regulated output voltage'],
                ['Rated Voltage', 'v', 'Manufacturer rated voltage'],
                ['Operating Current', 'a', 'Typical current draw'],
                ['Rated Current', 'a', 'Maximum rated current'],
                ['Leakage Current', 'ma', 'Standby leakage current'],
                ['Power Consumption', 'w', 'Typical power draw'],
                ['Rated Power', 'w', 'Continuous rated power'],
                ['Output Power', 'w', 'Delivered output power'],
                ['Frequency', 'hz', 'Operating frequency'],
                ['Switching Frequency', 'khz', 'Converter switching frequency'],
                ['Resistance', 'ohm', 'Nominal resistance'],
                ['Capacitance', 'uf', 'Nominal capacitance'],
                ['Battery Type', null, 'Chemistry and form factor'],
                ['Battery Life', 'h', 'Runtime per charge'],
                ['Charge Time', 'h', 'Time to full charge'],
            ],

            'Performance' => [
                ['Operating Temperature', 'c', 'Rated operating temperature range'],
                ['Storage Temperature', 'c', 'Safe storage temperature range'],
                ['Operating Humidity', 'pct', 'Rated relative humidity range'],
                ['Operating Pressure', 'bar', 'Nominal working pressure'],
                ['Maximum Pressure', 'bar', 'Maximum allowable pressure'],
                ['Rotational Speed', 'rpm', 'Nominal rotational speed'],
                ['Torque', 'nm', 'Rated torque'],
                ['Force', 'n', 'Rated force'],
                ['Noise Level', 'dba', 'Sound pressure level at 1 m'],
                ['Efficiency', 'pct', 'Conversion or energy efficiency'],
                ['Accuracy', 'pct', 'Measurement accuracy'],
                ['Response Time', 'ms', 'Typical response time'],
                ['Processing Speed', 'ghz', 'Processor clock speed'],
                ['Refresh Rate', 'hz', 'Display refresh rate'],
                ['Brightness', 'lm', 'Luminous flux'],
                ['Color Temperature', 'k', 'Correlated colour temperature'],
                ['Colour Rendering Index', null, 'CRI value (0-100)'],
                ['Duty Cycle', 'pct', 'Rated duty cycle'],
            ],

            'Materials' => [
                ['Material', null, 'Primary construction material'],
                ['Body Material', null, 'Enclosure or body material'],
                ['Housing Material', null, 'Housing material'],
                ['Contact Material', null, 'Electrical contact plating'],
                ['Coating', null, 'Surface coating or treatment'],
                ['Surface Finish', null, 'Finish and texture'],
                ['Colour', null, 'Product colour'],
                ['Material Composition', null, 'Full material breakdown'],
                ['Recycled Content', 'pct', 'Share of recycled material'],
            ],

            'Compliance & Certification' => [
                ['Certifications', null, 'Standards the product is certified to'],
                ['IP Rating', null, 'Ingress protection rating'],
                ['IK Rating', null, 'Impact protection rating'],
                ['RoHS Status', null, 'RoHS compliance'],
                ['REACH Status', null, 'REACH compliance'],
                ['Operating Altitude', 'm', 'Maximum rated altitude'],
                ['Warranty Period', 'mo', 'Manufacturer warranty'],
                ['Country of Origin', null, 'Country of manufacture'],
                ['Standards', null, 'Applicable standards'],
            ],

            'Interface & Connectivity' => [
                ['Interface', null, 'Signalling interface'],
                ['Protocol', null, 'Supported communication protocol'],
                ['Connector Type', null, 'Connector or termination style'],
                ['Number of Pins', 'pc', 'Pin or terminal count'],
                ['Number of Ports', 'pc', 'Port count'],
                ['Number of Channels', 'pc', 'Channel count'],
                ['Baud Rate', 'bps', 'Serial baud rate'],
                ['Data Rate', 'mbps', 'Maximum data throughput'],
                ['Wireless Standard', null, 'Wi-Fi, Bluetooth or cellular standard'],
            ],

            'Packaging & Logistics' => [
                ['Packaging Type', null, 'Carton, blister, bag or pallet'],
                ['Package Dimensions', 'mm', 'Outer package dimensions'],
                ['Units per Case', 'pc', 'Units in an outer case'],
                ['Minimum Order Quantity', 'pc', 'Smallest orderable quantity'],
                ['Shelf Life', 'mo', 'Usable shelf life'],
                ['Storage Conditions', null, 'Recommended storage environment'],
            ],
        ];
    }
}
