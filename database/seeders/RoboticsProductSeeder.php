<?php

namespace Database\Seeders;

use App\Enums\Sourcing;
use App\Models\Category;
use App\Models\Organization;
use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Seeds the Robotics & Automation launch catalog (from docs/robotics-plan.md).
 *
 * Every product is "Import on Demand" from China: sourcing = on_demand,
 * origin = CN, lead time = 18 days. Prices are illustrative USD and live in
 * `metadata.price`; the sourcing channel is stored in
 * `metadata.sourcing_channel`.
 */
class RoboticsProductSeeder extends Seeder
{
    public function run(): void
    {
        $organization = Organization::query()->first();

        if (! $organization) {
            $this->command->warn('⚠️  No organization found. Run OrganizationSeeder before RoboticsProductSeeder.');

            return;
        }

        $categories = Category::query()->pluck('id', 'slug');

        // Bulk seeding must not fire the search/SEO observers per product —
        // a reindex command covers the corpus afterwards.
        Product::withoutEvents(function () use ($organization, $categories): void {
            foreach ($this->products() as $item) {
                $this->seedProduct($organization, $categories, $item);
            }
        });

        $this->command->info('Robotics catalog seeded: '.count($this->products()).' products.');
        $this->command->warn('⚠️  The storefront searches Typesense — run `php artisan search:reindex --fresh` to index the new products.');
    }

    /**
     * @param  Collection<int, mixed>  $categories
     * @param  array{name: string, part: string, supplier: string, hs: string, category: string, price: float, channel: string}  $item
     */
    private function seedProduct(
        Organization $organization,
        Collection $categories,
        array $item,
    ): void {
        $slug = Str::slug($item['name']);

        $data = [
            'tenant_id' => $organization->tenant_id,
            'organization_id' => $organization->id,
            'name' => $item['name'],
            'slug' => $slug,
            'sku' => $this->sku($item['part']),
            'part_number' => $item['part'],
            'supplier_reference' => $item['supplier'],
            'hs_code' => $item['hs'],
            'sourcing' => Sourcing::ON_DEMAND,
            'origin_country' => 'CN',
            'lead_time_days' => 18,
            'status' => 'active',
            'is_active' => true,
            'metadata' => [
                'price' => (float) $item['price'],
                'currency' => 'USD',
                'sourcing_channel' => $item['channel'],
            ],
        ];

        // `withoutEvents` skips the model's `creating` hook, so the uuid —
        // normally assigned there — is set explicitly on insert only.
        $product = Product::query()->withTrashed()->where('slug', $slug)->first();

        if ($product) {
            $product->update($data);
        } else {
            $product = Product::query()->create($data + ['uuid' => (string) Str::uuid()]);
        }

        $categoryId = $categories[$item['category']] ?? null;
        if ($categoryId) {
            $product->categories()->sync([$categoryId]);
        }
    }

    private function sku(string $part): string
    {
        return Str::upper(Str::slug($part)) ?: 'RB';
    }

    /**
     * @return array<int, array{name: string, part: string, supplier: string, hs: string, category: string, price: float, channel: string}>
     */
    private function products(): array
    {
        return [
            // ── Educational & STEM Kits ──────────────────────────────
            ['name' => 'Arduino Uno R3 (clone)', 'part' => 'UNO R3', 'supplier' => 'HiLetgo', 'hs' => '8473.30', 'category' => 'educational-stem-kits', 'price' => 4, 'channel' => 'aliexpress'],
            ['name' => 'Arduino Mega 2560', 'part' => 'Mega 2560', 'supplier' => 'HiLetgo', 'hs' => '8473.30', 'category' => 'educational-stem-kits', 'price' => 12, 'channel' => 'aliexpress'],
            ['name' => 'Arduino Nano', 'part' => 'Nano', 'supplier' => 'HiLetgo', 'hs' => '8473.30', 'category' => 'educational-stem-kits', 'price' => 3, 'channel' => 'aliexpress'],
            ['name' => 'Raspberry Pi 4 Model B (4GB)', 'part' => 'RPi 4B 4GB', 'supplier' => 'Raspberry Pi', 'hs' => '8471.50', 'category' => 'educational-stem-kits', 'price' => 55, 'channel' => 'direct'],
            ['name' => 'Raspberry Pi Zero 2 W', 'part' => 'RPi Zero 2 W', 'supplier' => 'Raspberry Pi', 'hs' => '8471.50', 'category' => 'educational-stem-kits', 'price' => 15, 'channel' => 'direct'],
            ['name' => 'ESP32 DevKit v1', 'part' => 'ESP32 DevKit', 'supplier' => 'Espressif', 'hs' => '8542', 'category' => 'educational-stem-kits', 'price' => 5, 'channel' => 'aliexpress'],
            ['name' => 'STM32 Blue Pill', 'part' => 'STM32F103C8T6', 'supplier' => 'WeAct', 'hs' => '8542', 'category' => 'educational-stem-kits', 'price' => 3, 'channel' => 'aliexpress'],
            ['name' => 'Teensy 4.0', 'part' => 'Teensy 4.0', 'supplier' => 'PJRC', 'hs' => '8542', 'category' => 'educational-stem-kits', 'price' => 24, 'channel' => 'direct'],
            ['name' => 'Makeblock mBot2', 'part' => 'mBot2', 'supplier' => 'Makeblock', 'hs' => '9503', 'category' => 'educational-stem-kits', 'price' => 130, 'channel' => 'direct'],
            ['name' => 'SunFounder Arduino Starter Kit', 'part' => 'Starter Kit', 'supplier' => 'SunFounder', 'hs' => '9503', 'category' => 'educational-stem-kits', 'price' => 35, 'channel' => 'direct'],
            ['name' => 'ELEGOO UNO R3 Super Starter Kit', 'part' => 'UNO R3 Kit', 'supplier' => 'ELEGOO', 'hs' => '9503', 'category' => 'educational-stem-kits', 'price' => 30, 'channel' => 'direct'],
            ['name' => 'Yahboom Robot Car Kit (RPi)', 'part' => 'Robot Car Kit', 'supplier' => 'Yahboom', 'hs' => '9503', 'category' => 'educational-stem-kits', 'price' => 90, 'channel' => 'direct'],
            ['name' => 'Waveshare 37-in-1 Sensor Kit', 'part' => '37-in-1 Kit', 'supplier' => 'Waveshare', 'hs' => '8542', 'category' => 'educational-stem-kits', 'price' => 25, 'channel' => 'direct'],
            ['name' => 'Dobot Magician Lite', 'part' => 'Magician Lite', 'supplier' => 'Dobot', 'hs' => '9503', 'category' => 'educational-stem-kits', 'price' => 900, 'channel' => 'direct'],

            // ── Motors & Actuators ──────────────────────────────────
            ['name' => 'SG90 micro servo (9g)', 'part' => 'SG90', 'supplier' => 'EMAX', 'hs' => '8501.10', 'category' => 'motors-actuators', 'price' => 2, 'channel' => 'aliexpress'],
            ['name' => 'MG995 metal-gear servo', 'part' => 'MG995', 'supplier' => 'TowerPro', 'hs' => '8501', 'category' => 'motors-actuators', 'price' => 5, 'channel' => 'aliexpress'],
            ['name' => 'MG996R servo', 'part' => 'MG996R', 'supplier' => 'TowerPro', 'hs' => '8501', 'category' => 'motors-actuators', 'price' => 6, 'channel' => 'aliexpress'],
            ['name' => 'NEMA 17 stepper motor', 'part' => 'NEMA 17', 'supplier' => 'STEPPERONLINE', 'hs' => '8501', 'category' => 'motors-actuators', 'price' => 12, 'channel' => 'alibaba'],
            ['name' => 'NEMA 23 stepper motor', 'part' => 'NEMA 23', 'supplier' => 'STEPPERONLINE', 'hs' => '8501', 'category' => 'motors-actuators', 'price' => 25, 'channel' => 'alibaba'],
            ['name' => '28BYJ-48 stepper with ULN2003', 'part' => '28BYJ-48', 'supplier' => 'generic', 'hs' => '8501.10', 'category' => 'motors-actuators', 'price' => 3, 'channel' => 'aliexpress'],
            ['name' => 'TT DC gear motor (2-pack)', 'part' => 'TT Motor', 'supplier' => 'generic', 'hs' => '8501.10', 'category' => 'motors-actuators', 'price' => 2, 'channel' => 'aliexpress'],
            ['name' => '12V DC gear motor (60rpm)', 'part' => '12V 60RPM', 'supplier' => 'generic', 'hs' => '8501', 'category' => 'motors-actuators', 'price' => 8, 'channel' => 'aliexpress'],
            ['name' => 'T-Motor MN2212 920KV BLDC', 'part' => 'MN2212 920KV', 'supplier' => 'T-Motor', 'hs' => '8501', 'category' => 'motors-actuators', 'price' => 22, 'channel' => 'direct'],
            ['name' => 'T-Motor F60 Pro IV 2207', 'part' => 'F60 Pro IV', 'supplier' => 'T-Motor', 'hs' => '8501', 'category' => 'motors-actuators', 'price' => 25, 'channel' => 'direct'],
            ['name' => 'LX-16A bus servo', 'part' => 'LX-16A', 'supplier' => 'Hiwonder', 'hs' => '8501', 'category' => 'motors-actuators', 'price' => 35, 'channel' => 'direct'],

            // ── Sensors & Modules ────────────────────────────────────
            ['name' => 'HC-SR04 ultrasonic sensor', 'part' => 'HC-SR04', 'supplier' => 'generic', 'hs' => '8542', 'category' => 'sensors-modules', 'price' => 2, 'channel' => 'aliexpress'],
            ['name' => 'MPU6050 gyro/accelerometer', 'part' => 'MPU6050', 'supplier' => 'InvenSense', 'hs' => '8542', 'category' => 'sensors-modules', 'price' => 3, 'channel' => 'aliexpress'],
            ['name' => 'MPU9250 9-axis IMU', 'part' => 'MPU9250', 'supplier' => 'generic', 'hs' => '8542', 'category' => 'sensors-modules', 'price' => 5, 'channel' => 'aliexpress'],
            ['name' => 'RPLIDAR A1 360° LiDAR', 'part' => 'RPLIDAR A1', 'supplier' => 'SLAMTEC', 'hs' => '9014', 'category' => 'sensors-modules', 'price' => 99, 'channel' => 'direct'],
            ['name' => 'HC-05 Bluetooth module', 'part' => 'HC-05', 'supplier' => 'generic', 'hs' => '8542', 'category' => 'sensors-modules', 'price' => 3, 'channel' => 'aliexpress'],
            ['name' => 'NEO-6M GPS module', 'part' => 'NEO-6M', 'supplier' => 'u-blox', 'hs' => '8526', 'category' => 'sensors-modules', 'price' => 8, 'channel' => 'aliexpress'],
            ['name' => 'NEO-M8N GPS module', 'part' => 'NEO-M8N', 'supplier' => 'u-blox', 'hs' => '8526', 'category' => 'sensors-modules', 'price' => 25, 'channel' => 'alibaba'],
            ['name' => '5-channel IR line tracker', 'part' => 'IR Tracker', 'supplier' => 'generic', 'hs' => '8542', 'category' => 'sensors-modules', 'price' => 3, 'channel' => 'aliexpress'],
            ['name' => 'DHT22 temperature/humidity sensor', 'part' => 'DHT22', 'supplier' => 'Aosong', 'hs' => '8542', 'category' => 'sensors-modules', 'price' => 4, 'channel' => 'aliexpress'],
            ['name' => 'VL53L0X ToF distance sensor', 'part' => 'VL53L0X', 'supplier' => 'STMicro', 'hs' => '8542', 'category' => 'sensors-modules', 'price' => 5, 'channel' => 'aliexpress'],
            ['name' => 'Pixy2 vision camera', 'part' => 'Pixy2', 'supplier' => 'Charmed Labs', 'hs' => '8525', 'category' => 'sensors-modules', 'price' => 80, 'channel' => 'direct'],

            // ── Controllers & Boards ─────────────────────────────────
            ['name' => 'L298N motor driver', 'part' => 'L298N', 'supplier' => 'generic', 'hs' => '8542', 'category' => 'controllers-boards', 'price' => 2, 'channel' => 'aliexpress'],
            ['name' => 'A4988 stepper driver', 'part' => 'A4988', 'supplier' => 'generic', 'hs' => '8542', 'category' => 'controllers-boards', 'price' => 2, 'channel' => 'aliexpress'],
            ['name' => 'TB6600 stepper driver', 'part' => 'TB6600', 'supplier' => 'generic', 'hs' => '8542', 'category' => 'controllers-boards', 'price' => 9, 'channel' => 'aliexpress'],
            ['name' => 'Pixhawk 2.4.8 flight controller', 'part' => 'Pixhawk 2.4.8', 'supplier' => 'Holybro', 'hs' => '8526', 'category' => 'controllers-boards', 'price' => 120, 'channel' => 'alibaba'],
            ['name' => 'Pixhawk 6C flight controller', 'part' => 'Pixhawk 6C', 'supplier' => 'Holybro', 'hs' => '8526', 'category' => 'controllers-boards', 'price' => 250, 'channel' => 'direct'],
            ['name' => 'JIYI K++ flight controller', 'part' => 'JIYI K++', 'supplier' => 'Jiyi', 'hs' => '8526', 'category' => 'controllers-boards', 'price' => 110, 'channel' => 'direct'],

            // ── Power & Batteries ────────────────────────────────────
            ['name' => '18650 Li-ion cell (3000mAh)', 'part' => '18650 3000mAh', 'supplier' => 'Samsung', 'hs' => '8507.60', 'category' => 'power-batteries', 'price' => 4, 'channel' => 'alibaba'],
            ['name' => '3S 2200mAh LiPo pack', 'part' => '3S 2200mAh', 'supplier' => 'CNHL', 'hs' => '8507.60', 'category' => 'power-batteries', 'price' => 18, 'channel' => 'alibaba'],
            ['name' => '6S 5200mAh LiPo pack', 'part' => '6S 5200mAh', 'supplier' => 'CNHL', 'hs' => '8507.60', 'category' => 'power-batteries', 'price' => 55, 'channel' => 'alibaba'],
            ['name' => 'B6AC balance charger', 'part' => 'B6AC', 'supplier' => 'SkyRC', 'hs' => '8504', 'category' => 'power-batteries', 'price' => 35, 'channel' => 'aliexpress'],
            ['name' => '18650 battery holder', 'part' => '18650 holder', 'supplier' => 'generic', 'hs' => '8536', 'category' => 'power-batteries', 'price' => 2, 'channel' => 'aliexpress'],
            ['name' => 'XL4015 DC-DC buck converter', 'part' => 'XL4015', 'supplier' => 'generic', 'hs' => '8542', 'category' => 'power-batteries', 'price' => 2, 'channel' => 'aliexpress'],
            ['name' => 'LM2596 buck converter', 'part' => 'LM2596', 'supplier' => 'generic', 'hs' => '8542', 'category' => 'power-batteries', 'price' => 2, 'channel' => 'aliexpress'],
            ['name' => '3S/4S BMS protection board', 'part' => 'BMS 3S/4S', 'supplier' => 'generic', 'hs' => '8542', 'category' => 'power-batteries', 'price' => 3, 'channel' => 'aliexpress'],

            // ── Drone & UAV Parts ────────────────────────────────────
            ['name' => 'Carbon fiber prop 10x4.5 (pair)', 'part' => 'CF 10x4.5', 'supplier' => 'T-Motor', 'hs' => '8807', 'category' => 'drone-uav-parts', 'price' => 6, 'channel' => 'alibaba'],
            ['name' => 'Carbon fiber prop 15x5.5 (pair)', 'part' => 'CF 15x5.5', 'supplier' => 'generic', 'hs' => '8807', 'category' => 'drone-uav-parts', 'price' => 12, 'channel' => 'alibaba'],
            ['name' => '30A BLDC ESC (SimonK)', 'part' => '30A ESC', 'supplier' => 'generic', 'hs' => '8501', 'category' => 'drone-uav-parts', 'price' => 8, 'channel' => 'aliexpress'],
            ['name' => '45A BLDC ESC', 'part' => '45A ESC', 'supplier' => 'generic', 'hs' => '8501', 'category' => 'drone-uav-parts', 'price' => 12, 'channel' => 'aliexpress'],
            ['name' => 'Hobbywing XRotor 40A ESC', 'part' => 'XRotor 40A', 'supplier' => 'Hobbywing', 'hs' => '8501', 'category' => 'drone-uav-parts', 'price' => 20, 'channel' => 'direct'],
            ['name' => 'T-Motor F55A Pro II ESC', 'part' => 'F55A Pro II', 'supplier' => 'T-Motor', 'hs' => '8501', 'category' => 'drone-uav-parts', 'price' => 30, 'channel' => 'direct'],
            ['name' => 'RunCam Phoenix FPV camera', 'part' => 'Phoenix', 'supplier' => 'RunCam', 'hs' => '8525', 'category' => 'drone-uav-parts', 'price' => 35, 'channel' => 'direct'],
            ['name' => 'Multispectral crop camera', 'part' => 'Multispectral', 'supplier' => 'generic', 'hs' => '8525', 'category' => 'drone-uav-parts', 'price' => 1500, 'channel' => 'alibaba'],
            ['name' => '3-axis gimbal (action cam)', 'part' => '3-axis gimbal', 'supplier' => 'generic', 'hs' => '8807', 'category' => 'drone-uav-parts', 'price' => 90, 'channel' => 'alibaba'],
            ['name' => 'RadioMaster TX16S transmitter', 'part' => 'TX16S', 'supplier' => 'RadioMaster', 'hs' => '8526', 'category' => 'drone-uav-parts', 'price' => 200, 'channel' => 'direct'],
            ['name' => 'FrSky R-XSR receiver', 'part' => 'R-XSR', 'supplier' => 'FrSky', 'hs' => '8526', 'category' => 'drone-uav-parts', 'price' => 25, 'channel' => 'direct'],
            ['name' => 'Ublox F9P RTK GPS module', 'part' => 'F9P RTK', 'supplier' => 'u-blox', 'hs' => '8526', 'category' => 'drone-uav-parts', 'price' => 120, 'channel' => 'alibaba'],
            ['name' => 'F450 quad frame', 'part' => 'F450', 'supplier' => 'generic', 'hs' => '8807', 'category' => 'drone-uav-parts', 'price' => 15, 'channel' => 'aliexpress'],
            ['name' => '5-inch carbon FPV frame', 'part' => '5 inch CF', 'supplier' => 'generic', 'hs' => '8807', 'category' => 'drone-uav-parts', 'price' => 40, 'channel' => 'aliexpress'],

            // ── Agricultural Drone Components ────────────────────────
            ['name' => '10L agricultural spray tank', 'part' => '10L tank', 'supplier' => 'generic', 'hs' => '8424', 'category' => 'agricultural-drone-components', 'price' => 80, 'channel' => 'alibaba'],
            ['name' => '20L agricultural spray tank', 'part' => '20L tank', 'supplier' => 'generic', 'hs' => '8424', 'category' => 'agricultural-drone-components', 'price' => 120, 'channel' => 'alibaba'],
            ['name' => 'Heavy-lift BLDC motor (100kv)', 'part' => 'HL 100kv', 'supplier' => 'T-Motor', 'hs' => '8501', 'category' => 'agricultural-drone-components', 'price' => 90, 'channel' => 'direct'],
            ['name' => 'Agricultural spray nozzle set', 'part' => 'Nozzle set', 'supplier' => 'generic', 'hs' => '8424', 'category' => 'agricultural-drone-components', 'price' => 15, 'channel' => 'alibaba'],
            ['name' => 'Diaphragm spray pump', 'part' => 'Spray pump', 'supplier' => 'generic', 'hs' => '8413', 'category' => 'agricultural-drone-components', 'price' => 35, 'channel' => 'alibaba'],
            ['name' => 'Spray flow meter', 'part' => 'Flow meter', 'supplier' => 'generic', 'hs' => '9026', 'category' => 'agricultural-drone-components', 'price' => 20, 'channel' => 'alibaba'],

            // ── Robot Chassis & Wheels ───────────────────────────────
            ['name' => 'Mecanum wheel (80mm)', 'part' => 'Mecanum 80mm', 'supplier' => 'generic', 'hs' => '3926', 'category' => 'robot-chassis-wheels', 'price' => 18, 'channel' => 'alibaba'],
            ['name' => 'Omni wheel (48mm)', 'part' => 'Omni 48mm', 'supplier' => 'generic', 'hs' => '3926', 'category' => 'robot-chassis-wheels', 'price' => 8, 'channel' => 'aliexpress'],
            ['name' => '2WD robot chassis kit', 'part' => '2WD chassis', 'supplier' => 'generic', 'hs' => '9503', 'category' => 'robot-chassis-wheels', 'price' => 12, 'channel' => 'aliexpress'],
            ['name' => '4WD robot chassis kit', 'part' => '4WD chassis', 'supplier' => 'generic', 'hs' => '9503', 'category' => 'robot-chassis-wheels', 'price' => 18, 'channel' => 'aliexpress'],
            ['name' => 'Tank track chassis', 'part' => 'Tank chassis', 'supplier' => 'generic', 'hs' => '9503', 'category' => 'robot-chassis-wheels', 'price' => 40, 'channel' => 'alibaba'],
            ['name' => 'Servo robot gripper (claw)', 'part' => 'Servo gripper', 'supplier' => 'Hiwonder', 'hs' => '9503', 'category' => 'robot-chassis-wheels', 'price' => 15, 'channel' => 'direct'],

            // ── Industrial Automation ─────────────────────────────────
            ['name' => 'Siemens S7-1200 PLC', 'part' => 'S7-1200', 'supplier' => 'Siemens', 'hs' => '8537', 'category' => 'industrial-automation', 'price' => 300, 'channel' => 'direct'],
            ['name' => 'Delta DVP14SS PLC', 'part' => 'DVP14SS', 'supplier' => 'Delta', 'hs' => '8537', 'category' => 'industrial-automation', 'price' => 150, 'channel' => 'direct'],
            ['name' => 'Inductive proximity sensor', 'part' => 'Inductive prox', 'supplier' => 'Omron', 'hs' => '8536', 'category' => 'industrial-automation', 'price' => 10, 'channel' => 'direct'],
            ['name' => 'Safety laser scanner', 'part' => 'Laser scanner', 'supplier' => 'SICK', 'hs' => '9014', 'category' => 'industrial-automation', 'price' => 800, 'channel' => 'direct'],
            ['name' => 'AGV drive wheel with encoder', 'part' => 'AGV wheel', 'supplier' => 'generic', 'hs' => '8501', 'category' => 'industrial-automation', 'price' => 120, 'channel' => 'alibaba'],
            ['name' => 'Dobot Magician (desktop cobot)', 'part' => 'Dobot Magician', 'supplier' => 'Dobot', 'hs' => '8428', 'category' => 'industrial-automation', 'price' => 1200, 'channel' => 'direct'],
            ['name' => 'UFACTORY xArm 6', 'part' => 'xArm 6', 'supplier' => 'UFACTORY', 'hs' => '8428', 'category' => 'industrial-automation', 'price' => 5000, 'channel' => 'direct'],
            ['name' => 'Unitree Go2 robot dog', 'part' => 'Go2', 'supplier' => 'Unitree', 'hs' => '9503', 'category' => 'industrial-automation', 'price' => 1600, 'channel' => 'direct'],

            // ── Cables, Connectors & Hardware ────────────────────────
            ['name' => 'JST-XH connector kit', 'part' => 'JST-XH kit', 'supplier' => 'generic', 'hs' => '8536', 'category' => 'cables-connectors-hardware', 'price' => 8, 'channel' => 'aliexpress'],
            ['name' => 'XT60 connectors (10 pack)', 'part' => 'XT60', 'supplier' => 'generic', 'hs' => '8536', 'category' => 'cables-connectors-hardware', 'price' => 5, 'channel' => 'aliexpress'],
            ['name' => 'Dupont jumper wires (120pcs)', 'part' => 'Dupont wires', 'supplier' => 'generic', 'hs' => '8536', 'category' => 'cables-connectors-hardware', 'price' => 6, 'channel' => 'aliexpress'],
            ['name' => 'Silicone wire 14AWG (5m)', 'part' => '14AWG wire', 'supplier' => 'generic', 'hs' => '8544', 'category' => 'cables-connectors-hardware', 'price' => 8, 'channel' => 'aliexpress'],
            ['name' => 'Heat shrink tubing kit', 'part' => 'Heat shrink kit', 'supplier' => 'generic', 'hs' => '3926', 'category' => 'cables-connectors-hardware', 'price' => 6, 'channel' => 'aliexpress'],
            ['name' => 'M3 aluminum standoff kit', 'part' => 'M3 standoff', 'supplier' => 'generic', 'hs' => '7616', 'category' => 'cables-connectors-hardware', 'price' => 7, 'channel' => 'aliexpress'],
        ];
    }
}
