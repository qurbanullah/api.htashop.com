<?php

namespace Database\Seeders;

use App\Models\Measurement;
use App\Models\Tenant;
use App\Models\Unit;
use Illuminate\Database\Seeder;

class MeasurementSeeder extends Seeder
{
    public function run(): void
    {
        $tenants = Tenant::all();

        if ($tenants->isEmpty()) {
            $tenants = collect([
                Tenant::create([
                    'uuid' => (string) \Illuminate\Support\Str::uuid(),
                    'name' => 'Default Tenant',
                    'slug' => 'default',
                    'domain' => 'localhost',
                    'is_active' => true,
                    'settings' => [],
                ]),
            ]);

            $this->command->info('✅ Created default tenant for measurement seeding');
        }

        foreach ($tenants as $tenant) {
            foreach ($this->measurements() as $data) {
                $measurement = Measurement::firstOrCreate(
                    ['tenant_id' => $tenant->id, 'code' => $data['code']],
                    [
                        'uuid' => (string) \Illuminate\Support\Str::uuid(),
                        'name' => $data['name'],
                        'description' => $data['description'],
                        'is_active' => true,
                        'metadata' => $data['metadata'] ?? [],
                    ]
                );

                foreach ($data['units'] as $unitData) {
                    Unit::firstOrCreate(
                        ['measurement_id' => $measurement->id, 'code' => $unitData['code']],
                        [
                            'uuid' => (string) \Illuminate\Support\Str::uuid(),
                            'tenant_id' => $tenant->id,
                            'name' => $unitData['name'],
                            'symbol' => $unitData['symbol'],
                            'factor' => $unitData['factor'],
                            'offset' => $unitData['offset'] ?? 0,
                            'precision' => $unitData['precision'] ?? 2,
                            'is_active' => true,
                        ]
                    );
                }
            }
        }

        $this->command->info('✅ Seeded measurements and units for all tenants');
    }

    private function measurements(): array
    {
        return [

            // ──────────────────────────────────────────
            //  1. LENGTH
            // ──────────────────────────────────────────
            [
                'name' => 'Length',
                'code' => 'length',
                'description' => 'Linear distance and dimension measurements',
                'metadata' => ['dimension' => 'length', 'category' => 'physical'],
                'units' => [
                    ['name' => 'Kilometer', 'code' => 'km', 'symbol' => 'km', 'factor' => 1000],
                    ['name' => 'Meter', 'code' => 'm', 'symbol' => 'm', 'factor' => 1],
                    ['name' => 'Decimeter', 'code' => 'dm', 'symbol' => 'dm', 'factor' => 0.1],
                    ['name' => 'Centimeter', 'code' => 'cm', 'symbol' => 'cm', 'factor' => 0.01],
                    ['name' => 'Millimeter', 'code' => 'mm', 'symbol' => 'mm', 'factor' => 0.001],
                    ['name' => 'Micrometer', 'code' => 'um', 'symbol' => 'µm', 'factor' => 0.000001],
                    ['name' => 'Nanometer', 'code' => 'nm', 'symbol' => 'nm', 'factor' => 0.000000001],
                    ['name' => 'Mile', 'code' => 'mi', 'symbol' => 'mi', 'factor' => 1609.344],
                    ['name' => 'Yard', 'code' => 'yd', 'symbol' => 'yd', 'factor' => 0.9144],
                    ['name' => 'Foot', 'code' => 'ft', 'symbol' => 'ft', 'factor' => 0.3048],
                    ['name' => 'Inch', 'code' => 'in', 'symbol' => 'in', 'factor' => 0.0254],
                    ['name' => 'Nautical Mile', 'code' => 'nmi', 'symbol' => 'nmi', 'factor' => 1852],
                ],
            ],

            // ──────────────────────────────────────────
            //  2. MASS / WEIGHT
            // ──────────────────────────────────────────
            [
                'name' => 'Mass',
                'code' => 'mass',
                'description' => 'Weight and mass measurements for products and shipping',
                'metadata' => ['dimension' => 'mass', 'category' => 'physical'],
                'units' => [
                    ['name' => 'Metric Ton', 'code' => 't', 'symbol' => 't', 'factor' => 1000],
                    ['name' => 'Kilogram', 'code' => 'kg', 'symbol' => 'kg', 'factor' => 1],
                    ['name' => 'Gram', 'code' => 'g', 'symbol' => 'g', 'factor' => 0.001],
                    ['name' => 'Milligram', 'code' => 'mg', 'symbol' => 'mg', 'factor' => 0.000001],
                    ['name' => 'Microgram', 'code' => 'mcg', 'symbol' => 'µg', 'factor' => 0.000000001],
                    ['name' => 'Pound', 'code' => 'lb', 'symbol' => 'lb', 'factor' => 0.45359237],
                    ['name' => 'Ounce', 'code' => 'oz', 'symbol' => 'oz', 'factor' => 0.028349523125],
                    ['name' => 'Stone', 'code' => 'st', 'symbol' => 'st', 'factor' => 6.35029318],
                    ['name' => 'Short Ton', 'code' => 'ton_us', 'symbol' => 'ton', 'factor' => 907.18474],
                    ['name' => 'Long Ton', 'code' => 'ton_uk', 'symbol' => 'Lt', 'factor' => 1016.0469088],
                    ['name' => 'Carat', 'code' => 'ct', 'symbol' => 'ct', 'factor' => 0.0002],
                    ['name' => 'Grain', 'code' => 'gr', 'symbol' => 'gr', 'factor' => 0.00006479891],
                ],
            ],

            // ──────────────────────────────────────────
            //  3. VOLUME
            // ──────────────────────────────────────────
            [
                'name' => 'Volume',
                'code' => 'volume',
                'description' => 'Liquid and dry volume measurements',
                'metadata' => ['dimension' => 'volume', 'category' => 'physical'],
                'units' => [
                    ['name' => 'Cubic Meter', 'code' => 'm3', 'symbol' => 'm³', 'factor' => 1],
                    ['name' => 'Liter', 'code' => 'L', 'symbol' => 'L', 'factor' => 0.001],
                    ['name' => 'Milliliter', 'code' => 'mL', 'symbol' => 'mL', 'factor' => 0.000001],
                    ['name' => 'Cubic Centimeter', 'code' => 'cm3', 'symbol' => 'cm³', 'factor' => 0.000001],
                    ['name' => 'Cubic Foot', 'code' => 'ft3', 'symbol' => 'ft³', 'factor' => 0.028316846592],
                    ['name' => 'Cubic Inch', 'code' => 'in3', 'symbol' => 'in³', 'factor' => 0.000016387064],
                    ['name' => 'US Gallon', 'code' => 'gal_us', 'symbol' => 'gal', 'factor' => 0.003785411784],
                    ['name' => 'UK Gallon', 'code' => 'gal_uk', 'symbol' => 'gal (UK)', 'factor' => 0.00454609],
                    ['name' => 'US Quart', 'code' => 'qt_us', 'symbol' => 'qt', 'factor' => 0.000946352946],
                    ['name' => 'US Pint', 'code' => 'pt_us', 'symbol' => 'pt', 'factor' => 0.000473176473],
                    ['name' => 'US Cup', 'code' => 'cup_us', 'symbol' => 'cup', 'factor' => 0.0002365882365],
                    ['name' => 'US Fluid Ounce', 'code' => 'floz_us', 'symbol' => 'fl oz', 'factor' => 0.0000295735295625],
                    ['name' => 'US Tablespoon', 'code' => 'tbsp_us', 'symbol' => 'tbsp', 'factor' => 0.00001478676478125],
                    ['name' => 'US Teaspoon', 'code' => 'tsp_us', 'symbol' => 'tsp', 'factor' => 0.00000492892159375],
                    ['name' => 'Oil Barrel', 'code' => 'bbl', 'symbol' => 'bbl', 'factor' => 0.158987294928],
                ],
            ],

            // ──────────────────────────────────────────
            //  4. AREA
            // ──────────────────────────────────────────
            [
                'name' => 'Area',
                'code' => 'area',
                'description' => 'Surface area and land measurements',
                'metadata' => ['dimension' => 'area', 'category' => 'physical'],
                'units' => [
                    ['name' => 'Square Kilometer', 'code' => 'km2', 'symbol' => 'km²', 'factor' => 1000000],
                    ['name' => 'Hectare', 'code' => 'ha', 'symbol' => 'ha', 'factor' => 10000],
                    ['name' => 'Square Meter', 'code' => 'm2', 'symbol' => 'm²', 'factor' => 1],
                    ['name' => 'Square Centimeter', 'code' => 'cm2', 'symbol' => 'cm²', 'factor' => 0.0001],
                    ['name' => 'Square Millimeter', 'code' => 'mm2', 'symbol' => 'mm²', 'factor' => 0.000001],
                    ['name' => 'Square Mile', 'code' => 'mi2', 'symbol' => 'mi²', 'factor' => 2589988.110336],
                    ['name' => 'Acre', 'code' => 'acre', 'symbol' => 'acre', 'factor' => 4046.8564224],
                    ['name' => 'Square Yard', 'code' => 'yd2', 'symbol' => 'yd²', 'factor' => 0.83612736],
                    ['name' => 'Square Foot', 'code' => 'ft2', 'symbol' => 'ft²', 'factor' => 0.09290304],
                    ['name' => 'Square Inch', 'code' => 'in2', 'symbol' => 'in²', 'factor' => 0.00064516],
                ],
            ],

            // ──────────────────────────────────────────
            //  5. TEMPERATURE
            // ──────────────────────────────────────────
            [
                'name' => 'Temperature',
                'code' => 'temperature',
                'description' => 'Temperature measurements for products, storage, and shipping',
                'metadata' => ['dimension' => 'temperature', 'category' => 'physical'],
                'units' => [
                    ['name' => 'Celsius', 'code' => 'c', 'symbol' => '°C', 'factor' => 1, 'offset' => 0],
                    ['name' => 'Fahrenheit', 'code' => 'f', 'symbol' => '°F', 'factor' => 0.55555555555556, 'offset' => -32],
                    ['name' => 'Kelvin', 'code' => 'k', 'symbol' => 'K', 'factor' => 1, 'offset' => -273.15],
                ],
            ],

            // ──────────────────────────────────────────
            //  6. PRESSURE
            // ──────────────────────────────────────────
            [
                'name' => 'Pressure',
                'code' => 'pressure',
                'description' => 'Pressure measurements for fluids, gases, and mechanical systems',
                'metadata' => ['dimension' => 'pressure', 'category' => 'physical'],
                'units' => [
                    ['name' => 'Pascal', 'code' => 'pa', 'symbol' => 'Pa', 'factor' => 1],
                    ['name' => 'Hectopascal', 'code' => 'hpa', 'symbol' => 'hPa', 'factor' => 100],
                    ['name' => 'Kilopascal', 'code' => 'kpa', 'symbol' => 'kPa', 'factor' => 1000],
                    ['name' => 'Megapascal', 'code' => 'mpa', 'symbol' => 'MPa', 'factor' => 1000000],
                    ['name' => 'Bar', 'code' => 'bar', 'symbol' => 'bar', 'factor' => 100000],
                    ['name' => 'Millibar', 'code' => 'mbar', 'symbol' => 'mbar', 'factor' => 100],
                    ['name' => 'PSI', 'code' => 'psi', 'symbol' => 'psi', 'factor' => 6894.757293168],
                    ['name' => 'Atmosphere', 'code' => 'atm', 'symbol' => 'atm', 'factor' => 101325],
                    ['name' => 'Torr', 'code' => 'torr', 'symbol' => 'Torr', 'factor' => 133.3223684211],
                    ['name' => 'mmHg', 'code' => 'mmhg', 'symbol' => 'mmHg', 'factor' => 133.322387415],
                    ['name' => 'inHg', 'code' => 'inhg', 'symbol' => 'inHg', 'factor' => 3386.388640341],
                ],
            ],

            // ──────────────────────────────────────────
            //  7. ENERGY
            // ──────────────────────────────────────────
            [
                'name' => 'Energy',
                'code' => 'energy',
                'description' => 'Energy, work, and heat measurements',
                'metadata' => ['dimension' => 'energy', 'category' => 'physical'],
                'units' => [
                    ['name' => 'Joule', 'code' => 'j', 'symbol' => 'J', 'factor' => 1],
                    ['name' => 'Kilojoule', 'code' => 'kj', 'symbol' => 'kJ', 'factor' => 1000],
                    ['name' => 'Megajoule', 'code' => 'mj', 'symbol' => 'MJ', 'factor' => 1000000],
                    ['name' => 'Calorie', 'code' => 'cal', 'symbol' => 'cal', 'factor' => 4.184],
                    ['name' => 'Kilocalorie', 'code' => 'kcal', 'symbol' => 'kcal', 'factor' => 4184],
                    ['name' => 'Watt Hour', 'code' => 'wh', 'symbol' => 'Wh', 'factor' => 3600],
                    ['name' => 'Kilowatt Hour', 'code' => 'kwh', 'symbol' => 'kWh', 'factor' => 3600000],
                    ['name' => 'Electronvolt', 'code' => 'ev', 'symbol' => 'eV', 'factor' => 1.602176634e-19],
                    ['name' => 'BTU', 'code' => 'btu', 'symbol' => 'BTU', 'factor' => 1055.05585262],
                    ['name' => 'Therm', 'code' => 'therm', 'symbol' => 'therm', 'factor' => 105505585.262],
                ],
            ],

            // ──────────────────────────────────────────
            //  8. POWER
            // ──────────────────────────────────────────
            [
                'name' => 'Power',
                'code' => 'power',
                'description' => 'Power and energy rate measurements',
                'metadata' => ['dimension' => 'power', 'category' => 'physical'],
                'units' => [
                    ['name' => 'Watt', 'code' => 'w', 'symbol' => 'W', 'factor' => 1],
                    ['name' => 'Kilowatt', 'code' => 'kw', 'symbol' => 'kW', 'factor' => 1000],
                    ['name' => 'Megawatt', 'code' => 'mw', 'symbol' => 'MW', 'factor' => 1000000],
                    ['name' => 'Gigawatt', 'code' => 'gw', 'symbol' => 'GW', 'factor' => 1000000000],
                    ['name' => 'Horsepower', 'code' => 'hp', 'symbol' => 'hp', 'factor' => 745.69987158227],
                    ['name' => 'Metric Horsepower', 'code' => 'ps', 'symbol' => 'PS', 'factor' => 735.49875],
                    ['name' => 'BTU per Hour', 'code' => 'btu_h', 'symbol' => 'BTU/h', 'factor' => 0.29307107017222],
                    ['name' => 'Ton of Refrigeration', 'code' => 'tr', 'symbol' => 'TR', 'factor' => 3516.8528420667],
                ],
            ],

            // ──────────────────────────────────────────
            //  9. FORCE
            // ──────────────────────────────────────────
            [
                'name' => 'Force',
                'code' => 'force',
                'description' => 'Force and thrust measurements',
                'metadata' => ['dimension' => 'force', 'category' => 'physical'],
                'units' => [
                    ['name' => 'Newton', 'code' => 'n', 'symbol' => 'N', 'factor' => 1],
                    ['name' => 'Kilonewton', 'code' => 'kn', 'symbol' => 'kN', 'factor' => 1000],
                    ['name' => 'Pound-force', 'code' => 'lbf', 'symbol' => 'lbf', 'factor' => 4.4482216152605],
                    ['name' => 'Kilogram-force', 'code' => 'kgf', 'symbol' => 'kgf', 'factor' => 9.80665],
                    ['name' => 'Dyne', 'code' => 'dyn', 'symbol' => 'dyn', 'factor' => 0.00001],
                ],
            ],

            // ──────────────────────────────────────────
            // 10. TORQUE
            // ──────────────────────────────────────────
            [
                'name' => 'Torque',
                'code' => 'torque',
                'description' => 'Torque and moment of force measurements',
                'metadata' => ['dimension' => 'torque', 'category' => 'physical'],
                'units' => [
                    ['name' => 'Newton Meter', 'code' => 'nm', 'symbol' => 'N·m', 'factor' => 1],
                    ['name' => 'Kilonewton Meter', 'code' => 'knm', 'symbol' => 'kN·m', 'factor' => 1000],
                    ['name' => 'Pound-foot', 'code' => 'lbft', 'symbol' => 'lb·ft', 'factor' => 1.3558179483314],
                    ['name' => 'Pound-inch', 'code' => 'lbin', 'symbol' => 'lb·in', 'factor' => 0.11298482902762],
                    ['name' => 'Kilogram-meter', 'code' => 'kgm', 'symbol' => 'kg·m', 'factor' => 9.80665],
                ],
            ],

            // ──────────────────────────────────────────
            // 11. SPEED / VELOCITY
            // ──────────────────────────────────────────
            [
                'name' => 'Speed',
                'code' => 'speed',
                'description' => 'Speed and velocity measurements',
                'metadata' => ['dimension' => 'speed', 'category' => 'physical'],
                'units' => [
                    ['name' => 'Meter per Second', 'code' => 'm_s', 'symbol' => 'm/s', 'factor' => 1],
                    ['name' => 'Kilometer per Hour', 'code' => 'km_h', 'symbol' => 'km/h', 'factor' => 0.27777777777778],
                    ['name' => 'Mile per Hour', 'code' => 'mph', 'symbol' => 'mph', 'factor' => 0.44704],
                    ['name' => 'Knot', 'code' => 'kt', 'symbol' => 'kt', 'factor' => 0.51444444444444],
                    ['name' => 'Feet per Second', 'code' => 'ft_s', 'symbol' => 'ft/s', 'factor' => 0.3048],
                    ['name' => 'Mach', 'code' => 'mach', 'symbol' => 'Mach', 'factor' => 340.3],
                ],
            ],

            // ──────────────────────────────────────────
            // 12. FREQUENCY
            // ──────────────────────────────────────────
            [
                'name' => 'Frequency',
                'code' => 'frequency',
                'description' => 'Frequency and clock speed measurements',
                'metadata' => ['dimension' => 'frequency', 'category' => 'physical'],
                'units' => [
                    ['name' => 'Hertz', 'code' => 'hz', 'symbol' => 'Hz', 'factor' => 1],
                    ['name' => 'Kilohertz', 'code' => 'khz', 'symbol' => 'kHz', 'factor' => 1000],
                    ['name' => 'Megahertz', 'code' => 'mhz', 'symbol' => 'MHz', 'factor' => 1000000],
                    ['name' => 'Gigahertz', 'code' => 'ghz', 'symbol' => 'GHz', 'factor' => 1000000000],
                    ['name' => 'RPM', 'code' => 'rpm', 'symbol' => 'RPM', 'factor' => 0.016666666666667],
                ],
            ],

            // ──────────────────────────────────────────
            // 13. ANGLE
            // ──────────────────────────────────────────
            [
                'name' => 'Angle',
                'code' => 'angle',
                'description' => 'Angular measurements',
                'metadata' => ['dimension' => 'angle', 'category' => 'physical'],
                'units' => [
                    ['name' => 'Degree', 'code' => 'deg', 'symbol' => '°', 'factor' => 0.017453292519943],
                    ['name' => 'Radian', 'code' => 'rad', 'symbol' => 'rad', 'factor' => 1],
                    ['name' => 'Gradian', 'code' => 'grad', 'symbol' => 'grad', 'factor' => 0.015707963267949],
                ],
            ],

            // ──────────────────────────────────────────
            // 14. ELECTRIC CURRENT
            // ──────────────────────────────────────────
            [
                'name' => 'Electric Current',
                'code' => 'electric_current',
                'description' => 'Electrical current measurements',
                'metadata' => ['dimension' => 'electric_current', 'category' => 'electrical'],
                'units' => [
                    ['name' => 'Ampere', 'code' => 'a', 'symbol' => 'A', 'factor' => 1],
                    ['name' => 'Milliampere', 'code' => 'ma', 'symbol' => 'mA', 'factor' => 0.001],
                    ['name' => 'Microampere', 'code' => 'ua', 'symbol' => 'µA', 'factor' => 0.000001],
                    ['name' => 'Kiloampere', 'code' => 'ka', 'symbol' => 'kA', 'factor' => 1000],
                ],
            ],

            // ──────────────────────────────────────────
            // 15. VOLTAGE
            // ──────────────────────────────────────────
            [
                'name' => 'Voltage',
                'code' => 'voltage',
                'description' => 'Electrical voltage and potential difference measurements',
                'metadata' => ['dimension' => 'voltage', 'category' => 'electrical'],
                'units' => [
                    ['name' => 'Volt', 'code' => 'v', 'symbol' => 'V', 'factor' => 1],
                    ['name' => 'Millivolt', 'code' => 'mv', 'symbol' => 'mV', 'factor' => 0.001],
                    ['name' => 'Microvolt', 'code' => 'uv', 'symbol' => 'µV', 'factor' => 0.000001],
                    ['name' => 'Kilovolt', 'code' => 'kv', 'symbol' => 'kV', 'factor' => 1000],
                ],
            ],

            // ──────────────────────────────────────────
            // 16. ELECTRICAL RESISTANCE
            // ──────────────────────────────────────────
            [
                'name' => 'Electrical Resistance',
                'code' => 'resistance',
                'description' => 'Electrical resistance and impedance measurements',
                'metadata' => ['dimension' => 'resistance', 'category' => 'electrical'],
                'units' => [
                    ['name' => 'Ohm', 'code' => 'ohm', 'symbol' => 'Ω', 'factor' => 1],
                    ['name' => 'Milliohm', 'code' => 'mohm', 'symbol' => 'mΩ', 'factor' => 0.001],
                    ['name' => 'Kiloohm', 'code' => 'kohm', 'symbol' => 'kΩ', 'factor' => 1000],
                    ['name' => 'Megaohm', 'code' => 'mohm2', 'symbol' => 'MΩ', 'factor' => 1000000],
                ],
            ],

            // ──────────────────────────────────────────
            // 17. ELECTRICAL CAPACITANCE
            // ──────────────────────────────────────────
            [
                'name' => 'Capacitance',
                'code' => 'capacitance',
                'description' => 'Electrical capacitance measurements',
                'metadata' => ['dimension' => 'capacitance', 'category' => 'electrical'],
                'units' => [
                    ['name' => 'Farad', 'code' => 'f', 'symbol' => 'F', 'factor' => 1],
                    ['name' => 'Millifarad', 'code' => 'mf', 'symbol' => 'mF', 'factor' => 0.001],
                    ['name' => 'Microfarad', 'code' => 'uf', 'symbol' => 'µF', 'factor' => 0.000001],
                    ['name' => 'Nanofarad', 'code' => 'nf', 'symbol' => 'nF', 'factor' => 0.000000001],
                    ['name' => 'Picofarad', 'code' => 'pf', 'symbol' => 'pF', 'factor' => 0.000000000001],
                ],
            ],

            // ──────────────────────────────────────────
            // 18. BATTERY / CHARGE CAPACITY
            // ──────────────────────────────────────────
            [
                'name' => 'Battery Capacity',
                'code' => 'battery_capacity',
                'description' => 'Battery and electrical charge capacity measurements',
                'metadata' => ['dimension' => 'charge', 'category' => 'electrical'],
                'units' => [
                    ['name' => 'Ampere-hour', 'code' => 'ah', 'symbol' => 'Ah', 'factor' => 3600],
                    ['name' => 'Milliampere-hour', 'code' => 'mah', 'symbol' => 'mAh', 'factor' => 3.6],
                    ['name' => 'Watt-hour', 'code' => 'wh2', 'symbol' => 'Wh', 'factor' => 3600],
                ],
            ],

            // ──────────────────────────────────────────
            // 19. DATA STORAGE (base: Byte)
            // ──────────────────────────────────────────
            [
                'name' => 'Data Storage',
                'code' => 'data_storage',
                'description' => 'Digital data storage capacity measurements',
                'metadata' => ['dimension' => 'data', 'category' => 'digital'],
                'units' => [
                    ['name' => 'Byte', 'code' => 'b2', 'symbol' => 'B', 'factor' => 1],
                    ['name' => 'Kilobyte', 'code' => 'kb', 'symbol' => 'KB', 'factor' => 1000],
                    ['name' => 'Megabyte', 'code' => 'mb', 'symbol' => 'MB', 'factor' => 1000000],
                    ['name' => 'Gigabyte', 'code' => 'gb', 'symbol' => 'GB', 'factor' => 1000000000],
                    ['name' => 'Bit', 'code' => 'bit', 'symbol' => 'b', 'factor' => 0.125],
                ],
            ],

            // ──────────────────────────────────────────
            // 20. DATA TRANSFER RATE
            // ──────────────────────────────────────────
            [
                'name' => 'Data Transfer Rate',
                'code' => 'data_rate',
                'description' => 'Network speed and data transfer rate measurements',
                'metadata' => ['dimension' => 'data_rate', 'category' => 'digital'],
                'units' => [
                    ['name' => 'Bit per Second', 'code' => 'bps', 'symbol' => 'bps', 'factor' => 1],
                    ['name' => 'Kilobit per Second', 'code' => 'kbps', 'symbol' => 'Kbps', 'factor' => 1000],
                    ['name' => 'Megabit per Second', 'code' => 'mbps', 'symbol' => 'Mbps', 'factor' => 1000000],
                    ['name' => 'Gigabit per Second', 'code' => 'gbps', 'symbol' => 'Gbps', 'factor' => 1000000000],
                    ['name' => 'Megabyte per Second', 'code' => 'MBps', 'symbol' => 'MB/s', 'factor' => 8000000],
                    ['name' => 'Gigabyte per Second', 'code' => 'GBps', 'symbol' => 'GB/s', 'factor' => 8000000000],
                ],
            ],

            // ──────────────────────────────────────────
            // 21. SCREEN / DISPLAY SIZE
            // ──────────────────────────────────────────
            [
                'name' => 'Display Size',
                'code' => 'display_size',
                'description' => 'Screen and display diagonal size measurements',
                'metadata' => ['dimension' => 'length', 'category' => 'digital'],
                'units' => [
                    ['name' => 'Inch', 'code' => 'in2', 'symbol' => 'in', 'factor' => 0.0254],
                    ['name' => 'Centimeter', 'code' => 'cm2', 'symbol' => 'cm', 'factor' => 0.01],
                ],
            ],

            // ──────────────────────────────────────────
            // 22. RESOLUTION / PIXEL DENSITY
            // ──────────────────────────────────────────
            [
                'name' => 'Resolution',
                'code' => 'resolution',
                'description' => 'Screen resolution and pixel density measurements',
                'metadata' => ['dimension' => 'resolution', 'category' => 'digital'],
                'units' => [
                    ['name' => 'Pixel', 'code' => 'px', 'symbol' => 'px', 'factor' => 1],
                    ['name' => 'Megapixel', 'code' => 'mpx', 'symbol' => 'MP', 'factor' => 1000000],
                    ['name' => 'DPI', 'code' => 'dpi', 'symbol' => 'DPI', 'factor' => 1],
                    ['name' => 'PPI', 'code' => 'ppi', 'symbol' => 'PPI', 'factor' => 1],
                ],
            ],

            // ──────────────────────────────────────────
            // 23. DENSITY
            // ──────────────────────────────────────────
            [
                'name' => 'Density',
                'code' => 'density',
                'description' => 'Mass density and concentration measurements',
                'metadata' => ['dimension' => 'density', 'category' => 'physical'],
                'units' => [
                    ['name' => 'Kilogram per Cubic Meter', 'code' => 'kg_m3', 'symbol' => 'kg/m³', 'factor' => 1],
                    ['name' => 'Gram per Cubic Centimeter', 'code' => 'g_cm3', 'symbol' => 'g/cm³', 'factor' => 1000],
                    ['name' => 'Gram per Milliliter', 'code' => 'g_ml', 'symbol' => 'g/mL', 'factor' => 1000],
                    ['name' => 'Pound per Cubic Foot', 'code' => 'lb_ft3', 'symbol' => 'lb/ft³', 'factor' => 16.01846337396],
                    ['name' => 'Pound per Gallon', 'code' => 'lb_gal', 'symbol' => 'lb/gal', 'factor' => 119.8264273169],
                ],
            ],

            // ──────────────────────────────────────────
            // 24. FLOW RATE
            // ──────────────────────────────────────────
            [
                'name' => 'Flow Rate',
                'code' => 'flow_rate',
                'description' => 'Volumetric and mass flow rate measurements',
                'metadata' => ['dimension' => 'flow_rate', 'category' => 'physical'],
                'units' => [
                    ['name' => 'Cubic Meter per Second', 'code' => 'm3_s', 'symbol' => 'm³/s', 'factor' => 1],
                    ['name' => 'Liter per Second', 'code' => 'L_s', 'symbol' => 'L/s', 'factor' => 0.001],
                    ['name' => 'Liter per Minute', 'code' => 'L_min', 'symbol' => 'L/min', 'factor' => 0.000016666666667],
                    ['name' => 'Cubic Meter per Hour', 'code' => 'm3_h', 'symbol' => 'm³/h', 'factor' => 0.000277777777778],
                    ['name' => 'US GPM', 'code' => 'gpm', 'symbol' => 'GPM', 'factor' => 0.00006309019625],
                    ['name' => 'CFM', 'code' => 'cfm', 'symbol' => 'CFM', 'factor' => 0.0004719474432],
                ],
            ],

            // ──────────────────────────────────────────
            // 25. SOUND LEVEL
            // ──────────────────────────────────────────
            [
                'name' => 'Sound Level',
                'code' => 'sound_level',
                'description' => 'Sound pressure level and noise measurements',
                'metadata' => ['dimension' => 'sound', 'category' => 'physical'],
                'units' => [
                    ['name' => 'Decibel', 'code' => 'db', 'symbol' => 'dB', 'factor' => 1],
                    ['name' => 'dBA', 'code' => 'dba', 'symbol' => 'dBA', 'factor' => 1],
                    ['name' => 'dB SPL', 'code' => 'db_spl', 'symbol' => 'dB SPL', 'factor' => 1],
                ],
            ],

            // ──────────────────────────────────────────
            // 26. ILLUMINANCE / LIGHT
            // ──────────────────────────────────────────
            [
                'name' => 'Light',
                'code' => 'light',
                'description' => 'Luminous flux, illuminance, and light output measurements',
                'metadata' => ['dimension' => 'light', 'category' => 'physical'],
                'units' => [
                    ['name' => 'Lumen', 'code' => 'lm', 'symbol' => 'lm', 'factor' => 1],
                    ['name' => 'Lux', 'code' => 'lx', 'symbol' => 'lx', 'factor' => 1],
                    ['name' => 'Candela', 'code' => 'cd', 'symbol' => 'cd', 'factor' => 1],
                    ['name' => 'Lumen per Watt', 'code' => 'lm_w', 'symbol' => 'lm/W', 'factor' => 1],
                ],
            ],

            // ──────────────────────────────────────────
            // 27. TIME
            // ──────────────────────────────────────────
            [
                'name' => 'Time',
                'code' => 'time',
                'description' => 'Duration and time interval measurements',
                'metadata' => ['dimension' => 'time', 'category' => 'physical'],
                'units' => [
                    ['name' => 'Second', 'code' => 's', 'symbol' => 's', 'factor' => 1],
                    ['name' => 'Millisecond', 'code' => 'ms', 'symbol' => 'ms', 'factor' => 0.001],
                    ['name' => 'Microsecond', 'code' => 'us', 'symbol' => 'µs', 'factor' => 0.000001],
                    ['name' => 'Nanosecond', 'code' => 'ns', 'symbol' => 'ns', 'factor' => 0.000000001],
                    ['name' => 'Minute', 'code' => 'min', 'symbol' => 'min', 'factor' => 60],
                    ['name' => 'Hour', 'code' => 'h', 'symbol' => 'h', 'factor' => 3600],
                    ['name' => 'Day', 'code' => 'd', 'symbol' => 'd', 'factor' => 86400],
                    ['name' => 'Week', 'code' => 'wk', 'symbol' => 'wk', 'factor' => 604800],
                    ['name' => 'Month', 'code' => 'mo', 'symbol' => 'mo', 'factor' => 2629800],
                    ['name' => 'Year', 'code' => 'yr', 'symbol' => 'yr', 'factor' => 31557600],
                ],
            ],

            // ──────────────────────────────────────────
            // 28. PACKAGING / COUNTING UNITS
            // ──────────────────────────────────────────
            [
                'name' => 'Packaging',
                'code' => 'packaging',
                'description' => 'Packaging, counting, and bulk quantity units for e-commerce',
                'metadata' => ['dimension' => 'count', 'category' => 'commerce'],
                'units' => [
                    ['name' => 'Piece', 'code' => 'pc', 'symbol' => 'pc', 'factor' => 1],
                    ['name' => 'Pair', 'code' => 'pr', 'symbol' => 'pr', 'factor' => 2],
                    ['name' => 'Dozen', 'code' => 'doz', 'symbol' => 'doz', 'factor' => 12],
                    ['name' => 'Half Dozen', 'code' => 'half_doz', 'symbol' => 'half-doz', 'factor' => 6],
                    ['name' => 'Gross', 'code' => 'gr2', 'symbol' => 'gross', 'factor' => 144],
                    ['name' => 'Pack', 'code' => 'pack', 'symbol' => 'pack', 'factor' => 1],
                    ['name' => 'Box', 'code' => 'box', 'symbol' => 'box', 'factor' => 1],
                    ['name' => 'Carton', 'code' => 'ctn', 'symbol' => 'ctn', 'factor' => 1],
                    ['name' => 'Case', 'code' => 'case', 'symbol' => 'case', 'factor' => 1],
                    ['name' => 'Pallet', 'code' => 'plt', 'symbol' => 'plt', 'factor' => 1],
                    ['name' => 'Roll', 'code' => 'roll', 'symbol' => 'roll', 'factor' => 1],
                    ['name' => 'Sheet', 'code' => 'sheet', 'symbol' => 'sheet', 'factor' => 1],
                    ['name' => 'Set', 'code' => 'set', 'symbol' => 'set', 'factor' => 1],
                    ['name' => 'Kit', 'code' => 'kit', 'symbol' => 'kit', 'factor' => 1],
                    ['name' => 'Bundle', 'code' => 'bundle', 'symbol' => 'bundle', 'factor' => 1],
                    ['name' => 'Ream', 'code' => 'ream', 'symbol' => 'ream', 'factor' => 500],
                    ['name' => 'Spool', 'code' => 'spool', 'symbol' => 'spool', 'factor' => 1],
                    ['name' => 'Coil', 'code' => 'coil', 'symbol' => 'coil', 'factor' => 1],
                    ['name' => 'Tube', 'code' => 'tube', 'symbol' => 'tube', 'factor' => 1],
                    ['name' => 'Ampoule', 'code' => 'amp', 'symbol' => 'amp', 'factor' => 1],
                    ['name' => 'Vial', 'code' => 'vial', 'symbol' => 'vial', 'factor' => 1],
                    ['name' => 'Sachet', 'code' => 'sachet', 'symbol' => 'sachet', 'factor' => 1],
                    ['name' => 'Envelope', 'code' => 'env', 'symbol' => 'env', 'factor' => 1],
                    ['name' => 'Bag', 'code' => 'bag', 'symbol' => 'bag', 'factor' => 1],
                    ['name' => 'Can', 'code' => 'can', 'symbol' => 'can', 'factor' => 1],
                    ['name' => 'Bottle', 'code' => 'bottle', 'symbol' => 'bottle', 'factor' => 1],
                    ['name' => 'Jar', 'code' => 'jar', 'symbol' => 'jar', 'factor' => 1],
                    ['name' => 'Drum', 'code' => 'drum', 'symbol' => 'drum', 'factor' => 1],
                    ['name' => 'Container', 'code' => 'container', 'symbol' => 'container', 'factor' => 1],
                    ['name' => 'Barrel', 'code' => 'barrel', 'symbol' => 'barrel', 'factor' => 1],
                ],
            ],

            // ──────────────────────────────────────────
            // 29. CONCENTRATION
            // ──────────────────────────────────────────
            [
                'name' => 'Concentration',
                'code' => 'concentration',
                'description' => 'Chemical concentration and solution measurements',
                'metadata' => ['dimension' => 'concentration', 'category' => 'chemical'],
                'units' => [
                    ['name' => 'Percent', 'code' => 'pct', 'symbol' => '%', 'factor' => 1],
                    ['name' => 'Parts per Million', 'code' => 'ppm', 'symbol' => 'ppm', 'factor' => 0.0001],
                    ['name' => 'Parts per Billion', 'code' => 'ppb', 'symbol' => 'ppb', 'factor' => 0.0000001],
                    ['name' => 'Mole per Liter', 'code' => 'mol_L', 'symbol' => 'mol/L', 'factor' => 1],
                    ['name' => 'Gram per Liter', 'code' => 'g_L', 'symbol' => 'g/L', 'factor' => 1],
                    ['name' => 'Milligram per Liter', 'code' => 'mg_L', 'symbol' => 'mg/L', 'factor' => 0.001],
                ],
            ],

            // ──────────────────────────────────────────
            // 30. TEXTILE / FABRIC
            // ──────────────────────────────────────────
            [
                'name' => 'Textile',
                'code' => 'textile',
                'description' => 'Textile, fabric, and thread measurements',
                'metadata' => ['dimension' => 'textile', 'category' => 'commerce'],
                'units' => [
                    ['name' => 'Denier', 'code' => 'den', 'symbol' => 'den', 'factor' => 1],
                    ['name' => 'Tex', 'code' => 'tex', 'symbol' => 'tex', 'factor' => 1],
                    ['name' => 'Thread Count', 'code' => 'tc', 'symbol' => 'TC', 'factor' => 1],
                    ['name' => 'GSM', 'code' => 'gsm', 'symbol' => 'gsm', 'factor' => 1],
                    ['name' => 'Gauge', 'code' => 'gauge', 'symbol' => 'gauge', 'factor' => 1],
                    ['name' => 'Yarn Count', 'code' => 'ne', 'symbol' => 'Ne', 'factor' => 1],
                ],
            ],

            // ──────────────────────────────────────────
            // 31. PAPER / PRINTING
            // ──────────────────────────────────────────
            [
                'name' => 'Paper',
                'code' => 'paper',
                'description' => 'Paper weight, thickness, and print measurements',
                'metadata' => ['dimension' => 'paper', 'category' => 'commerce'],
                'units' => [
                    ['name' => 'GSM (Paper)', 'code' => 'gsm2', 'symbol' => 'gsm', 'factor' => 1],
                    ['name' => 'Point', 'code' => 'pt', 'symbol' => 'pt', 'factor' => 0.001],
                    ['name' => 'Micron', 'code' => 'micron', 'symbol' => 'µ', 'factor' => 0.001],
                    ['name' => 'Caliper', 'code' => 'caliper', 'symbol' => 'caliper', 'factor' => 0.0254],
                ],
            ],
        ];
    }
}
