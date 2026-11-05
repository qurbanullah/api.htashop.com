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
        $definitions = $this->features();
        $idsByCode = $this->categoryIdsByCode($definitions);

        foreach ($definitions as $name => $categoryCodes) {
            $feature = Feature::updateOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name, 'is_active' => true],
            );

            $ids = collect($categoryCodes)
                ->map(fn (string $code): ?int => $idsByCode[$code] ?? null)
                ->filter()
                ->unique()
                ->values()
                ->all();

            // `sync` keeps the seeded mapping authoritative when the taxonomy moves.
            $feature->categories()->sync($ids);
        }

        $this->command?->info(sprintf(
            'Seeded %d product features across %d categories.',
            count($definitions),
            count($idsByCode),
        ));
    }

    /**
     * Resolve every referenced category code once, warning about drift between
     * this file and {@see CategorySeeder}.
     *
     * @param  array<string, array<int, string>>  $definitions
     * @return array<string, int>
     */
    private function categoryIdsByCode(array $definitions): array
    {
        $codes = collect($definitions)->flatten()->unique()->values();
        $idsByCode = Category::query()->whereIn('code', $codes)->pluck('id', 'code')->all();

        $unknown = $codes->diff(array_keys($idsByCode));

        if ($unknown->isNotEmpty()) {
            $this->command?->warn(
                'FeatureSeeder references unknown category codes: '.$unknown->implode(', ')
            );
        }

        return $idsByCode;
    }

    /**
     * Feature name => top-level category codes it belongs to.
     *
     * Features are a shared vocabulary: a product may pick any of them, and the
     * category links below only drive the "recommended for this category"
     * ordering in the product wizard.
     *
     * @return array<string, array<int, string>>
     */
    private function features(): array
    {
        return [
            // ── Electronics (EC) ──
            'ESD Safe' => ['EC', 'IT', 'RB'],
            'Surface Mount' => ['EC'],
            'Through-Hole' => ['EC'],
            'Breadboard Compatible' => ['EC', 'RB', 'ED'],
            'I2C Interface' => ['EC', 'RB'],
            'SPI Interface' => ['EC', 'RB'],
            'UART Interface' => ['EC', 'RB'],
            'Programmable' => ['EC', 'RB', 'IT'],
            'Low Power' => ['EC', 'IT', 'RB', 'EL'],
            'Wide Input Voltage' => ['EC', 'EL'],
            'Shielded' => ['EC', 'IT'],
            'RoHS Compliant' => ['EC', 'IT', 'EL'],
            'Solderless' => ['EC', 'RB'],
            'Plug and Play' => ['EC', 'IT', 'RB'],
            'Precision Machined' => ['EC', 'MR'],
            'Calibrated' => ['EC', 'LB'],
            'Precision' => ['EC', 'LB', 'MR'],

            // ── Information Technology (IT) ──
            'Wireless' => ['IT', 'EC', 'RB'],
            'Bluetooth' => ['IT', 'EC', 'RB'],
            'Bluetooth LE' => ['IT', 'EC', 'RB'],
            'Wi-Fi 6' => ['IT'],
            'USB-C' => ['IT', 'EC'],
            'Thunderbolt' => ['IT'],
            'HDMI' => ['IT', 'EC'],
            'DisplayPort' => ['IT'],
            'Gigabit Ethernet' => ['IT'],
            'PoE Powered' => ['IT', 'EL'],
            'Touchscreen' => ['IT', 'ED'],
            'Backlit Keyboard' => ['IT'],
            'Noise Cancelling' => ['IT'],
            'Rechargeable Battery' => ['IT', 'EC', 'RB'],
            'Rack Mountable' => ['IT', 'EL'],
            'Hot Swappable' => ['IT'],
            'TPM 2.0' => ['IT'],
            'Kensington Lock' => ['IT'],
            'VESA Mount' => ['IT', 'FU'],
            'HDR' => ['IT'],
            'Low Noise' => ['IT', 'EL', 'CJ'],
            'Fanless' => ['IT', 'EL'],
            'Night Vision' => ['IT'],
            'Motion Detection' => ['IT', 'EL'],
            'Duplex Printing' => ['IT'],
            'Network Ready' => ['IT'],
            'Cloud Managed' => ['IT'],
            'Solid State Drive' => ['IT'],
            'Compatible with Laser Printers' => ['IT', 'OS'],
            'Compatible with Inkjet Printers' => ['IT', 'OS'],
            'OEM Compatible' => ['IT', 'AF'],
            'Anti-Vibration' => ['IT', 'MR'],
            'AI Enabled' => ['IT', 'RB'],

            // ── Office Supplies (OS) ──
            'Acid-Free Paper' => ['OS', 'PP'],
            'Archival Quality' => ['OS'],
            'Heavyweight' => ['OS', 'PS', 'PP'],
            'Smudge Resistant' => ['OS', 'PP'],
            'Quick-Dry Ink' => ['OS'],
            'Retractable' => ['OS'],
            'Laminated' => ['OS', 'PP'],
            'Lockable' => ['OS', 'FU'],
            'FSC Certified' => ['OS', 'BC', 'PP'],

            // ── Furniture (FU) ──
            'Ergonomic' => ['FU', 'IT'],
            'Adjustable Height' => ['FU'],
            'Foldable' => ['FU', 'SR'],
            'Wall Mountable' => ['FU', 'EL', 'PL'],
            'Stackable' => ['FU', 'PS', 'FB'],
            'Lumbar Support' => ['FU'],
            'Breathable Mesh' => ['FU', 'TA'],
            'Scratch Resistant' => ['FU', 'BC'],
            'Assembly Required' => ['FU'],
            'Stainless Steel' => ['FU', 'MR', 'PL', 'LB'],
            'Adjustable' => ['FU', 'SR', 'MD'],
            'Easy Assembly' => ['FU', 'GL'],

            // ── MRO & Industrial (MR) ──
            'High Temperature Resistant' => ['MR'],
            'Heavy Duty' => ['MR', 'FU', 'AF'],
            'Impact Resistant' => ['MR', 'SP', 'BC'],
            'Corrosion Resistant' => ['MR', 'PL', 'CL'],
            'Cordless' => ['MR', 'GL', 'CJ'],
            'Variable Speed' => ['MR'],
            'Brushless Motor' => ['MR', 'RB'],
            'Hardened' => ['MR'],
            'Tool-Free Installation' => ['MR', 'IT', 'FU', 'RB'],

            // ── Safety & PPE (SP) ──
            'IP67' => ['SP', 'EC', 'IT', 'EL'],
            'Waterproof' => ['SP', 'EC', 'IT', 'MR', 'EL'],
            'Dustproof' => ['SP', 'EC', 'IT', 'MR'],
            'Shockproof' => ['SP', 'EC', 'IT', 'MR'],
            'Flame Resistant' => ['SP', 'TA'],
            'Fire Rated' => ['SP', 'BC'],
            'Anti-Slip' => ['SP', 'SR'],
            'Hypoallergenic' => ['SP', 'MD'],
            'ANSI Certified' => ['SP'],
            'CE Certified' => ['SP', 'EL', 'MD', 'IT'],
            'Hi-Vis' => ['SP', 'TA'],
            'Anti-Fog' => ['SP'],
            'Cut Resistant' => ['SP'],
            'Chemical Resistant' => ['SP', 'CJ', 'LB'],
            'Arc Flash Rated' => ['SP', 'EL'],
            'Static Dissipative' => ['SP', 'EC'],
            'Latex Free' => ['SP', 'MD'],
            'Disposable' => ['SP', 'MD', 'CJ', 'LB', 'FB'],
            'Nitrile' => ['SP', 'MD'],
            'Fluid Resistant' => ['SP', 'MD'],
            'Antimicrobial' => ['SP', 'MD', 'CJ'],

            // ── Cleaning & Janitorial (CJ) ──
            'Concentrated' => ['CJ', 'CL'],
            'Streak Free' => ['CJ'],
            'pH Neutral' => ['CJ'],
            'Microfiber' => ['CJ'],
            'Lint Free' => ['CJ', 'LB', 'MD'],
            'Touch-Free' => ['CJ', 'MD'],
            'Scented' => ['CJ'],
            'Biodegradable' => ['CJ', 'PS', 'CL', 'GL'],
            'Non-Toxic' => ['CJ', 'ED', 'GL'],
            'Disinfectant' => ['CJ'],
            'Fragrance Free' => ['CJ'],
            'Easy Clean' => ['CJ', 'ED', 'FU'],

            // ── Packaging & Shipping (PS) ──
            'Cushioned' => ['PS'],
            'Tamper Evident' => ['PS'],
            'Moisture Resistant' => ['PS', 'BC'],
            'Recycled Content' => ['PS', 'OS', 'PP'],
            'Corrugated' => ['PS'],
            'Double Wall' => ['PS'],
            'Self-Seal' => ['PS'],
            'Printable' => ['PS', 'PP', 'TA'],
            'Food Safe' => ['PS', 'FB', 'CL'],
            'Compression Resistant' => ['PS'],

            // ── Lab & Scientific (LB) ──
            'Lab Grade' => ['LB'],
            'Aseptic' => ['LB', 'MD'],
            'Sterile' => ['LB', 'MD'],
            'Autoclavable' => ['LB', 'MD'],
            'Borosilicate Glass' => ['LB'],
            'Single Use' => ['LB', 'MD'],
            'Digital Display' => ['LB', 'MD', 'IT'],
            'Temperature Controlled' => ['LB', 'MD', 'FB'],
            'Gas Tight' => ['LB'],

            // ── Medical & Healthcare (MD) ──
            'Medical Grade' => ['MD'],
            'Reusable' => ['MD', 'ED', 'CJ'],
            'Non-Sterile' => ['MD'],
            'Oxygen Compatible' => ['MD'],

            // ── Food Service & Breakroom (FB) ──
            'Organic' => ['FB', 'GL'],
            'Fair Trade' => ['FB'],
            'Non-GMO' => ['FB'],
            'Gluten Free' => ['FB'],
            'Vegan' => ['FB'],
            'Kosher' => ['FB'],
            'Halal' => ['FB'],
            'Microwave Safe' => ['FB'],
            'Freezer Safe' => ['FB'],
            'Dishwasher Safe' => ['FB'],
            'Compostable' => ['FB', 'CJ', 'PS'],
            'BPA Free' => ['FB', 'OS'],
            'Single Serve' => ['FB'],
            'Commercial Grade' => ['FB', 'CJ', 'MR'],

            // ── Electrical & Lighting (EL) ──
            'Energy Star' => ['EL', 'IT', 'FU'],
            'Dimmable' => ['EL'],
            'LED' => ['EL'],
            'Surge Protected' => ['EL', 'IT'],
            'IP65' => ['EL', 'SP'],
            'Motion Sensor' => ['EL'],
            'Dusk to Dawn' => ['EL'],
            'Solar Powered' => ['EL', 'GL'],
            'Weatherproof' => ['EL', 'PP', 'BC'],
            'Tamper Resistant' => ['EL'],
            'UL Listed' => ['EL'],
            'Flicker Free' => ['EL'],
            'CCT Selectable' => ['EL'],
            'Emergency Backup' => ['EL', 'SP'],

            // ── Plumbing & HVAC (PL) ──
            'Lead Free' => ['PL'],
            'Low Flow' => ['PL'],
            'High Pressure' => ['PL', 'CJ'],
            'Frost Resistant' => ['PL', 'BC', 'GL'],
            'NSF Certified' => ['PL', 'FB'],
            'WaterSense' => ['PL'],
            'Energy Efficient' => ['PL', 'EL', 'IT', 'FU'],
            'Noise Reducing' => ['PL', 'IT'],
            'Adjustable Temperature' => ['PL', 'FB'],

            // ── Building & Construction (BC) ──
            'Load Bearing' => ['BC'],
            'Insulated' => ['BC', 'EL'],
            'Weather Resistant' => ['BC', 'AF', 'GL', 'SR'],
            'UV Resistant' => ['BC', 'SP', 'GL'],
            'Low Maintenance' => ['BC', 'GL'],
            'Pre-Finished' => ['BC'],
            'Soundproof' => ['BC'],
            'Water Based' => ['BC', 'CL'],

            // ── Automotive & Fleet (AF) ──
            'Rust Resistant' => ['AF', 'GL'],
            'All Weather' => ['AF'],
            'Heavy Duty Towing' => ['AF'],
            'Extended Life' => ['AF'],
            'Synthetic' => ['AF', 'CL'],
            'DOT Approved' => ['AF'],
            'Winter Rated' => ['AF'],

            // ── Textiles, Linens & Apparel (TA) ──
            'Breathable' => ['TA'],
            'Stretchable' => ['TA'],
            'Wrinkle Resistant' => ['TA'],
            'One Size' => ['TA'],
            'Water Repellent' => ['TA', 'SR'],
            'Machine Washable' => ['TA'],
            'Colourfast' => ['TA'],
            'Moisture Wicking' => ['TA', 'SR'],
            'Embroidered' => ['TA', 'PP'],
            'Organic Cotton' => ['TA'],

            // ── Sports & Recreation (SR) ──
            'Portable' => ['SR', 'IT'],
            'Durable' => ['SR', 'MR'],
            'Lightweight' => ['SR', 'IT', 'RB'],
            'UV Protection' => ['SR', 'TA', 'GL'],
            'Shock Absorbing' => ['SR'],
            'Quick Dry' => ['SR', 'TA'],

            // ── Educational Supplies (ED) ──
            'Child Safe' => ['ED'],
            'Age Appropriate' => ['ED'],
            'Battery Operated' => ['ED', 'RB'],
            'Washable' => ['ED', 'TA'],
            'STEM Focused' => ['ED', 'RB'],
            'Large Print' => ['ED'],

            // ── Chemicals & Lubricants (CL) ──
            'High Viscosity' => ['CL'],
            'Low VOC' => ['CL', 'BC'],
            'Fast Drying' => ['CL'],
            'Food Grade' => ['CL', 'FB'],
            'Solvent Based' => ['CL'],
            'Long Lasting' => ['CL', 'CJ'],
            'Non-Flammable' => ['CL', 'CJ'],
            'Spray Application' => ['CL'],

            // ── Printing & Promotional (PP) ──
            'Customizable' => ['PP'],
            'High Resolution' => ['PP'],
            'Full Color' => ['PP'],
            'Double Sided' => ['PP'],
            'Eco Friendly' => ['PP', 'OS', 'ED', 'CJ', 'GL'],
            'Recyclable' => ['PP', 'PS', 'OS'],

            // ── Gardening & Landscaping (GL) ──
            'Drought Resistant' => ['GL'],
            'Pet Safe' => ['GL', 'CJ'],
            'Self-Watering' => ['GL'],

            // ── Robotics & Automation (RB) ──
            'Autonomous' => ['RB'],
            'ROS Compatible' => ['RB'],
            'PID Control' => ['RB'],
            'Servo Controlled' => ['RB'],
            'Real-Time Control' => ['RB'],
            'Obstacle Avoidance' => ['RB'],
            'Remote Controlled' => ['RB'],
            'Precision Control' => ['RB'],
            'Modular Design' => ['RB', 'EC'],
        ];
    }
}
