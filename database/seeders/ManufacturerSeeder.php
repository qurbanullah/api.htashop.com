<?php

namespace Database\Seeders;

use App\Models\Manufacturer;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ManufacturerSeeder extends Seeder
{
    public function run(): void
    {
        $manufacturers = [];

        foreach (array_merge($this->core(), $this->catalogue()) as $manufacturer) {
            $manufacturers[Str::slug($manufacturer['name'])] = $manufacturer;
        }

        foreach ($this->wmc() as $manufacturer) {
            $manufacturers[Str::slug($manufacturer['name'])] ??= $manufacturer + ['type' => 'manufacturer'];
        }

        foreach ($manufacturers as $manufacturer) {
            $attributes = [
                'name' => $manufacturer['name'],
                'type' => $manufacturer['type'] ?? 'manufacturer',
                'is_active' => true,
            ];

            foreach (['code', 'country', 'website'] as $field) {
                if (! empty($manufacturer[$field])) {
                    $attributes[$field] = $manufacturer[$field];
                }
            }

            Manufacturer::updateOrCreate(['slug' => Str::slug($manufacturer['name'])], $attributes);
        }

        $this->command->info(sprintf(
            'Manufacturers seeded: %d (%d OEM/catalogue, %d WMC).',
            count($manufacturers),
            count($this->core()) + count($this->catalogue()),
            count($this->wmc()),
        ));
    }

    /**
     * Contract manufacturers and the OEMs behind the brands this store sells.
     *
     * @return array<int, array{name: string, code: string, type: string, country: string}>
     */
    private function core(): array
    {
        return [
            ['name' => 'Foxconn', 'code' => 'FXCN', 'type' => 'oem', 'country' => 'Taiwan'],
            ['name' => 'Pegatron', 'code' => 'PEG', 'type' => 'oem', 'country' => 'Taiwan'],
            ['name' => 'Quanta Computer', 'code' => 'QUTA', 'type' => 'oem', 'country' => 'Taiwan'],
            ['name' => 'Wistron', 'code' => 'WST', 'type' => 'oem', 'country' => 'Taiwan'],
            ['name' => 'Compal Electronics', 'code' => 'CMPL', 'type' => 'oem', 'country' => 'Taiwan'],
            ['name' => 'Inventec', 'code' => 'INVT', 'type' => 'oem', 'country' => 'Taiwan'],
            ['name' => 'Flex', 'code' => 'FLEX', 'type' => 'oem', 'country' => 'Singapore'],
            ['name' => 'Jabil', 'code' => 'JABL', 'type' => 'oem', 'country' => 'United States'],
            ['name' => 'Sanmina', 'code' => 'SANM', 'type' => 'oem', 'country' => 'United States'],
            ['name' => 'Celestica', 'code' => 'CELS', 'type' => 'oem', 'country' => 'Canada'],
            ['name' => 'Benchmark Electronics', 'code' => 'BHE', 'type' => 'oem', 'country' => 'United States'],
            ['name' => 'Plexus', 'code' => 'PLXS', 'type' => 'oem', 'country' => 'United States'],
            ['name' => 'Lite-On Technology', 'code' => 'LTON', 'type' => 'oem', 'country' => 'Taiwan'],
            ['name' => 'Chicony Electronics', 'code' => 'CHIC', 'type' => 'oem', 'country' => 'Taiwan'],
            ['name' => 'TPV Technology', 'code' => 'TPV', 'type' => 'oem', 'country' => 'Hong Kong'],
            ['name' => 'Qisda', 'code' => 'QISD', 'type' => 'oem', 'country' => 'Taiwan'],
            ['name' => 'Clevo', 'code' => 'CLEV', 'type' => 'oem', 'country' => 'Taiwan'],
            ['name' => 'MiTAC', 'code' => 'MITA', 'type' => 'oem', 'country' => 'Taiwan'],
            ['name' => 'Amtran Technology', 'code' => 'AMTR', 'type' => 'oem', 'country' => 'Taiwan'],
            ['name' => 'Delta Electronics', 'code' => 'DELT', 'type' => 'oem', 'country' => 'Taiwan'],
        ];
    }

    /**
     * Semiconductor, component, power, automation and robotics manufacturers.
     * The WMC list covers construction and agricultural machinery, so the
     * electronics supply chain has to be curated here.
     *
     * @return array<int, array{name: string, code: string, type: string, country: string}>
     */
    private function catalogue(): array
    {
        return [
            // ── Semiconductors and components ────────────────────────────
            ['name' => 'TSMC', 'code' => 'TSMC', 'type' => 'manufacturer', 'country' => 'Taiwan'],
            ['name' => 'SK hynix', 'code' => 'SKHY', 'type' => 'manufacturer', 'country' => 'South Korea'],
            ['name' => 'Micron Technology', 'code' => 'MICR', 'type' => 'manufacturer', 'country' => 'United States'],
            ['name' => 'Intel', 'code' => 'INTL', 'type' => 'manufacturer', 'country' => 'United States'],
            ['name' => 'AMD', 'code' => 'AMD', 'type' => 'manufacturer', 'country' => 'United States'],
            ['name' => 'NVIDIA', 'code' => 'NVDA', 'type' => 'manufacturer', 'country' => 'United States'],
            ['name' => 'Qualcomm', 'code' => 'QCOM', 'type' => 'manufacturer', 'country' => 'United States'],
            ['name' => 'Broadcom', 'code' => 'BRCM', 'type' => 'manufacturer', 'country' => 'United States'],
            ['name' => 'MediaTek', 'code' => 'MTK', 'type' => 'manufacturer', 'country' => 'Taiwan'],
            ['name' => 'Texas Instruments', 'code' => 'TXN', 'type' => 'manufacturer', 'country' => 'United States'],
            ['name' => 'STMicroelectronics', 'code' => 'STM', 'type' => 'manufacturer', 'country' => 'Switzerland'],
            ['name' => 'NXP Semiconductors', 'code' => 'NXP', 'type' => 'manufacturer', 'country' => 'Netherlands'],
            ['name' => 'Infineon Technologies', 'code' => 'IFX', 'type' => 'manufacturer', 'country' => 'Germany'],
            ['name' => 'Renesas Electronics', 'code' => 'REN', 'type' => 'manufacturer', 'country' => 'Japan'],
            ['name' => 'Microchip Technology', 'code' => 'MCP', 'type' => 'manufacturer', 'country' => 'United States'],
            ['name' => 'Analog Devices', 'code' => 'ADI', 'type' => 'manufacturer', 'country' => 'United States'],
            ['name' => 'onsemi', 'code' => 'ONS', 'type' => 'manufacturer', 'country' => 'United States'],
            ['name' => 'Vishay Intertechnology', 'code' => 'VSH', 'type' => 'manufacturer', 'country' => 'United States'],
            ['name' => 'Diodes Incorporated', 'code' => 'DIOD', 'type' => 'manufacturer', 'country' => 'United States'],
            ['name' => 'Murata Manufacturing', 'code' => 'MURA', 'type' => 'manufacturer', 'country' => 'Japan'],
            ['name' => 'TDK', 'code' => 'TDK', 'type' => 'manufacturer', 'country' => 'Japan'],
            ['name' => 'Yageo', 'code' => 'YAGO', 'type' => 'manufacturer', 'country' => 'Taiwan'],
            ['name' => 'KEMET', 'code' => 'KEM', 'type' => 'manufacturer', 'country' => 'United States'],
            ['name' => 'Nichicon', 'code' => 'NICH', 'type' => 'manufacturer', 'country' => 'Japan'],
            ['name' => 'TE Connectivity', 'code' => 'TEC', 'type' => 'manufacturer', 'country' => 'Switzerland'],
            ['name' => 'Amphenol', 'code' => 'APH', 'type' => 'manufacturer', 'country' => 'United States'],
            ['name' => 'Molex', 'code' => 'MOLEX', 'type' => 'manufacturer', 'country' => 'United States'],
            ['name' => 'JST', 'code' => 'JST', 'type' => 'manufacturer', 'country' => 'Japan'],
            ['name' => 'Hirose Electric', 'code' => 'HRS', 'type' => 'manufacturer', 'country' => 'Japan'],
            ['name' => 'Würth Elektronik', 'code' => 'WUR', 'type' => 'manufacturer', 'country' => 'Germany'],
            ['name' => 'Phoenix Contact', 'code' => 'PHC', 'type' => 'manufacturer', 'country' => 'Germany'],
            ['name' => 'Mean Well', 'code' => 'MW', 'type' => 'manufacturer', 'country' => 'Taiwan'],
            ['name' => 'Espressif Systems', 'code' => 'ESP', 'type' => 'manufacturer', 'country' => 'China'],
            ['name' => 'Nordic Semiconductor', 'code' => 'NORD', 'type' => 'manufacturer', 'country' => 'Norway'],
            ['name' => 'Silicon Labs', 'code' => 'SLAB', 'type' => 'manufacturer', 'country' => 'United States'],
            ['name' => 'u-blox', 'code' => 'UBX', 'type' => 'manufacturer', 'country' => 'Switzerland'],
            ['name' => 'Quectel', 'code' => 'QTL', 'type' => 'manufacturer', 'country' => 'China'],
            ['name' => 'Arduino', 'code' => 'ARDU', 'type' => 'manufacturer', 'country' => 'Italy'],
            ['name' => 'Raspberry Pi', 'code' => 'RPI', 'type' => 'manufacturer', 'country' => 'United Kingdom'],

            // ── Power supplies ───────────────────────────────────────────
            ['name' => 'Vicor', 'code' => 'VICOR', 'type' => 'manufacturer', 'country' => 'United States'],
            ['name' => 'XP Power', 'code' => 'XP', 'type' => 'manufacturer', 'country' => 'United Kingdom'],
            ['name' => 'TRACO Power', 'code' => 'TRACO', 'type' => 'manufacturer', 'country' => 'Switzerland'],
            ['name' => 'RECOM Power', 'code' => 'RECOM', 'type' => 'manufacturer', 'country' => 'Austria'],
            ['name' => 'Cincon Electronics', 'code' => 'CIN', 'type' => 'manufacturer', 'country' => 'Taiwan'],
            ['name' => 'Mornsun', 'code' => 'MORNSUN', 'type' => 'manufacturer', 'country' => 'China'],

            // ── Industrial automation ────────────────────────────────────
            ['name' => 'Siemens', 'code' => 'SIEM', 'type' => 'manufacturer', 'country' => 'Germany'],
            ['name' => 'Schneider Electric', 'code' => 'SCHN', 'type' => 'manufacturer', 'country' => 'France'],
            ['name' => 'ABB', 'code' => 'ABB', 'type' => 'manufacturer', 'country' => 'Switzerland'],
            ['name' => 'Rockwell Automation', 'code' => 'ROK', 'type' => 'manufacturer', 'country' => 'United States'],
            ['name' => 'Mitsubishi Electric', 'code' => 'MEL', 'type' => 'manufacturer', 'country' => 'Japan'],
            ['name' => 'Omron', 'code' => 'OMR', 'type' => 'manufacturer', 'country' => 'Japan'],
            ['name' => 'Bosch', 'code' => 'BSH', 'type' => 'manufacturer', 'country' => 'Germany'],
            ['name' => 'Bosch Rexroth', 'code' => 'REX', 'type' => 'manufacturer', 'country' => 'Germany'],
            ['name' => 'Festo', 'code' => 'FESTO', 'type' => 'manufacturer', 'country' => 'Germany'],
            ['name' => 'SMC Corporation', 'code' => 'SMC', 'type' => 'manufacturer', 'country' => 'Japan'],
            ['name' => 'Parker Hannifin', 'code' => 'PARK', 'type' => 'manufacturer', 'country' => 'United States'],
            ['name' => 'Eaton', 'code' => 'ETN', 'type' => 'manufacturer', 'country' => 'Ireland'],
            ['name' => 'Emerson', 'code' => 'EMR', 'type' => 'manufacturer', 'country' => 'United States'],
            ['name' => 'Honeywell', 'code' => 'HON', 'type' => 'manufacturer', 'country' => 'United States'],
            ['name' => 'Yokogawa', 'code' => 'YOKO', 'type' => 'manufacturer', 'country' => 'Japan'],
            ['name' => 'Endress+Hauser', 'code' => 'EHL', 'type' => 'manufacturer', 'country' => 'Switzerland'],
            ['name' => 'Pepperl+Fuchs', 'code' => 'PF', 'type' => 'manufacturer', 'country' => 'Germany'],
            ['name' => 'SICK', 'code' => 'SICK', 'type' => 'manufacturer', 'country' => 'Germany'],
            ['name' => 'Keyence', 'code' => 'KEY', 'type' => 'manufacturer', 'country' => 'Japan'],
            ['name' => 'Balluff', 'code' => 'BLF', 'type' => 'manufacturer', 'country' => 'Germany'],
            ['name' => 'Turck', 'code' => 'TURK', 'type' => 'manufacturer', 'country' => 'Germany'],
            ['name' => 'ifm electronic', 'code' => 'IFM', 'type' => 'manufacturer', 'country' => 'Germany'],
            ['name' => 'Pilz', 'code' => 'PILZ', 'type' => 'manufacturer', 'country' => 'Germany'],
            ['name' => 'Schmersal', 'code' => 'SCHM', 'type' => 'manufacturer', 'country' => 'Germany'],
            ['name' => 'Leuze', 'code' => 'LEUZ', 'type' => 'manufacturer', 'country' => 'Germany'],
            ['name' => 'WAGO', 'code' => 'WAGO', 'type' => 'manufacturer', 'country' => 'Germany'],
            ['name' => 'Beckhoff Automation', 'code' => 'BECK', 'type' => 'manufacturer', 'country' => 'Germany'],
            ['name' => 'B&R Industrial Automation', 'code' => 'BNR', 'type' => 'manufacturer', 'country' => 'Austria'],
            ['name' => 'Carlo Gavazzi', 'code' => 'CG', 'type' => 'manufacturer', 'country' => 'Switzerland'],
            ['name' => 'Finder', 'code' => 'FND', 'type' => 'manufacturer', 'country' => 'Italy'],
            ['name' => 'Legrand', 'code' => 'LEG', 'type' => 'manufacturer', 'country' => 'France'],
            ['name' => 'Hager', 'code' => 'HGR', 'type' => 'manufacturer', 'country' => 'Germany'],
            ['name' => 'Autonics', 'code' => 'AUTC', 'type' => 'manufacturer', 'country' => 'South Korea'],
            ['name' => 'LS Electric', 'code' => 'LSE', 'type' => 'manufacturer', 'country' => 'South Korea'],
            ['name' => 'Fatek', 'code' => 'FATEK', 'type' => 'manufacturer', 'country' => 'Taiwan'],
            ['name' => 'Sensirion', 'code' => 'SEN', 'type' => 'manufacturer', 'country' => 'Switzerland'],
            ['name' => 'Basler', 'code' => 'BAS', 'type' => 'manufacturer', 'country' => 'Germany'],
            ['name' => 'Cognex', 'code' => 'CGNX', 'type' => 'manufacturer', 'country' => 'United States'],

            // ── Robotics ─────────────────────────────────────────────────
            ['name' => 'FANUC', 'code' => 'FAN', 'type' => 'manufacturer', 'country' => 'Japan'],
            ['name' => 'KUKA', 'code' => 'KUKA', 'type' => 'manufacturer', 'country' => 'Germany'],
            ['name' => 'Yaskawa', 'code' => 'YASK', 'type' => 'manufacturer', 'country' => 'Japan'],
            ['name' => 'Universal Robots', 'code' => 'UR', 'type' => 'manufacturer', 'country' => 'Denmark'],

            // ── Consumer electronics manufacturing ───────────────────────
            ['name' => 'Sony', 'code' => 'SONY', 'type' => 'manufacturer', 'country' => 'Japan'],
            ['name' => 'Panasonic', 'code' => 'PANA', 'type' => 'manufacturer', 'country' => 'Japan'],
            ['name' => 'Philips', 'code' => 'PHIL', 'type' => 'manufacturer', 'country' => 'Netherlands'],
            ['name' => 'Toshiba', 'code' => 'TOS', 'type' => 'manufacturer', 'country' => 'Japan'],
            ['name' => 'LG', 'code' => 'LG', 'type' => 'manufacturer', 'country' => 'South Korea'],
            ['name' => 'Samsung', 'code' => 'SAMS', 'type' => 'manufacturer', 'country' => 'South Korea'],
            ['name' => '3M', 'code' => '3M', 'type' => 'manufacturer', 'country' => 'United States'],
        ];
    }

    /**
     * World Manufacturer Codes from the ISO 10261 public record list.
     *
     * @return array<int, array{name: string, code: string, country: string|null, website: string|null}>
     */
    private function wmc(): array
    {
        return require database_path('data/wmc-manufacturers-2026.php');
    }
}
