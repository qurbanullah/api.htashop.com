<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Manufacturer;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class BrandSeeder extends Seeder
{
    /**
     * Brand slug => manufacturer name, for brands whose production partner is
     * known. Kept out of the ranking data file because Brand Finance does not
     * publish manufacturing relationships.
     */
    private const MANUFACTURERS = [
        'apple' => 'Foxconn',
        'dell' => 'Quanta Computer',
        'dell-technologies' => 'Quanta Computer',
        'hp' => 'Wistron',
        'hpe' => 'Foxconn',
        'lenovo' => 'Wistron',
        'asus' => 'Pegatron',
        'acer' => 'Quanta Computer',
        'samsung' => 'Samsung',
        'lg' => 'LG',
        'sony' => 'Sony',
        'bosch' => 'Bosch',
        'siemens' => 'Siemens',
        'schneider-electric' => 'Schneider Electric',
        'apc' => 'Schneider Electric',
        'emerson' => 'Emerson',
        '3m' => '3M',
        'honeywell' => 'Honeywell',
        'abb' => 'ABB',
        'panasonic' => 'Panasonic',
        'philips' => 'Philips',
        'toshiba' => 'Toshiba',
    ];

    public function run(): void
    {
        $manufacturerIds = Manufacturer::query()
            ->get(['id', 'slug'])
            ->pluck('id', 'slug');

        $brands = [];

        foreach ($this->brands() as $brand) {
            $brands[Str::slug($brand['name'])] = $brand;
        }

        foreach ($brands as $slug => $brand) {
            $attributes = [
                'name' => $brand['name'],
                'country' => $brand['country'],
                'is_active' => true,
            ];

            $manufacturerSlug = self::MANUFACTURERS[$slug] ?? null;

            if ($manufacturerSlug !== null) {
                $attributes['manufacturer_id'] = $manufacturerIds[Str::slug($manufacturerSlug)] ?? null;
            }

            Brand::updateOrCreate(['slug' => $slug], $attributes);
        }

        $this->command->info(sprintf(
            'Brands seeded: %d (%d ranked, %d catalogue).',
            count($brands),
            count($this->ranking()),
            count($this->catalogue()),
        ));
    }

    /**
     * Ranked brands first (industry-agnostic), then the electronics and
     * industrial catalogue this store actually sells. Duplicates are collapsed
     * by slug in `run()`, so the catalogue may repeat a ranked brand safely.
     *
     * @return array<int, array{name: string, country: string}>
     */
    private function brands(): array
    {
        return array_merge($this->ranking(), $this->catalogue());
    }

    /**
     * Brand Finance Global 500, 2026 edition.
     *
     * @return array<int, array{name: string, country: string}>
     */
    private function ranking(): array
    {
        return require database_path('data/brandirectory-global-2026.php');
    }

    /**
     * Electronics, embedded, industrial automation, test equipment, computing
     * and networking brands. These are not all present in the Global 500, which
     * skews towards consumer and financial brands, so they are curated here.
     *
     * @return array<int, array{name: string, country: string}>
     */
    private function catalogue(): array
    {
        return [
            // ── Embedded, dev boards and MCU ─────────────────────────────
            ['name' => 'Arduino', 'country' => 'Italy'],
            ['name' => 'Raspberry Pi', 'country' => 'United Kingdom'],
            ['name' => 'Espressif Systems', 'country' => 'China'],
            ['name' => 'STMicroelectronics', 'country' => 'Switzerland'],
            ['name' => 'Microchip Technology', 'country' => 'United States'],
            ['name' => 'NXP Semiconductors', 'country' => 'Netherlands'],
            ['name' => 'Infineon Technologies', 'country' => 'Germany'],
            ['name' => 'Renesas Electronics', 'country' => 'Japan'],
            ['name' => 'Analog Devices', 'country' => 'United States'],
            ['name' => 'Nordic Semiconductor', 'country' => 'Norway'],
            ['name' => 'Silicon Labs', 'country' => 'United States'],
            ['name' => 'Seeed Studio', 'country' => 'China'],
            ['name' => 'Adafruit', 'country' => 'United States'],
            ['name' => 'SparkFun Electronics', 'country' => 'United States'],
            ['name' => 'Pololu', 'country' => 'United States'],
            ['name' => 'DFRobot', 'country' => 'China'],
            ['name' => 'Waveshare', 'country' => 'China'],
            ['name' => 'Elegoo', 'country' => 'China'],
            ['name' => 'SunFounder', 'country' => 'China'],
            ['name' => 'Keyestudio', 'country' => 'China'],
            ['name' => 'Makeblock', 'country' => 'China'],
            ['name' => 'LOLIN', 'country' => 'China'],
            ['name' => 'u-blox', 'country' => 'Switzerland'],
            ['name' => 'Quectel', 'country' => 'China'],
            ['name' => 'SimCom', 'country' => 'China'],
            ['name' => 'Telit', 'country' => 'Italy'],
            ['name' => 'Digi International', 'country' => 'United States'],
            ['name' => 'Particle', 'country' => 'United States'],
            ['name' => 'PJRC', 'country' => 'United States'],
            ['name' => 'BeagleBoard.org', 'country' => 'United States'],
            ['name' => 'Olimex', 'country' => 'Bulgaria'],
            ['name' => 'Hardkernel', 'country' => 'South Korea'],
            ['name' => 'Radxa', 'country' => 'China'],
            ['name' => 'Banana Pi', 'country' => 'China'],
            ['name' => 'Orange Pi', 'country' => 'China'],

            // ── Components, passives and electromechanical ────────────────
            ['name' => 'Vishay Intertechnology', 'country' => 'United States'],
            ['name' => 'Diodes Incorporated', 'country' => 'United States'],
            ['name' => 'onsemi', 'country' => 'United States'],
            ['name' => 'Murata Manufacturing', 'country' => 'Japan'],
            ['name' => 'TDK', 'country' => 'Japan'],
            ['name' => 'Yageo', 'country' => 'Taiwan'],
            ['name' => 'KEMET', 'country' => 'United States'],
            ['name' => 'Nichicon', 'country' => 'Japan'],
            ['name' => 'Rubycon', 'country' => 'Japan'],
            ['name' => 'TE Connectivity', 'country' => 'Switzerland'],
            ['name' => 'Amphenol', 'country' => 'United States'],
            ['name' => 'Molex', 'country' => 'United States'],
            ['name' => 'JST', 'country' => 'Japan'],
            ['name' => 'Hirose Electric', 'country' => 'Japan'],
            ['name' => 'Würth Elektronik', 'country' => 'Germany'],
            ['name' => 'Phoenix Contact', 'country' => 'Germany'],
            ['name' => 'Weidmüller', 'country' => 'Germany'],
            ['name' => 'Degson', 'country' => 'China'],
            ['name' => 'Samtec', 'country' => 'United States'],
            ['name' => 'Harwin', 'country' => 'United Kingdom'],
            ['name' => 'Bulgin', 'country' => 'United Kingdom'],
            ['name' => 'Neutrik', 'country' => 'Liechtenstein'],
            ['name' => 'Switchcraft', 'country' => 'United States'],
            ['name' => 'HARTING', 'country' => 'Germany'],
            ['name' => 'LAPP', 'country' => 'Germany'],
            ['name' => 'igus', 'country' => 'Germany'],
            ['name' => 'Belden', 'country' => 'United States'],
            ['name' => 'Alpha Wire', 'country' => 'United States'],
            ['name' => 'Schurter', 'country' => 'Switzerland'],
            ['name' => 'Littelfuse', 'country' => 'United States'],
            ['name' => 'Bourns', 'country' => 'United States'],
            ['name' => 'Alps Alpine', 'country' => 'Japan'],
            ['name' => 'Nidec', 'country' => 'Japan'],

            // ── Power supplies and batteries ──────────────────────────────
            ['name' => 'Delta Electronics', 'country' => 'Taiwan'],
            ['name' => 'Mean Well', 'country' => 'Taiwan'],
            ['name' => 'XP Power', 'country' => 'United Kingdom'],
            ['name' => 'TRACO Power', 'country' => 'Switzerland'],
            ['name' => 'RECOM Power', 'country' => 'Austria'],
            ['name' => 'Cincon Electronics', 'country' => 'Taiwan'],
            ['name' => 'Mornsun', 'country' => 'China'],
            ['name' => 'Vicor', 'country' => 'United States'],
            ['name' => 'TDK-Lambda', 'country' => 'Japan'],
            ['name' => 'SL Power Electronics', 'country' => 'United States'],
            ['name' => 'CUI Inc', 'country' => 'United States'],
            ['name' => 'APC', 'country' => 'United States'],
            ['name' => 'CyberPower', 'country' => 'United States'],
            ['name' => 'Vertiv', 'country' => 'United States'],
            ['name' => 'Duracell', 'country' => 'United States'],
            ['name' => 'Energizer', 'country' => 'United States'],
            ['name' => 'Samsung SDI', 'country' => 'South Korea'],
            ['name' => 'LG Energy Solution', 'country' => 'South Korea'],
            ['name' => 'XTAR', 'country' => 'China'],
            ['name' => 'Nitecore', 'country' => 'China'],
            ['name' => 'Jinko Solar', 'country' => 'China'],
            ['name' => 'Trina Solar', 'country' => 'China'],
            ['name' => 'LONGi', 'country' => 'China'],
            ['name' => 'Canadian Solar', 'country' => 'Canada'],

            // ── Test, measurement and hand tools ──────────────────────────
            ['name' => 'Fluke', 'country' => 'United States'],
            ['name' => 'Keysight Technologies', 'country' => 'United States'],
            ['name' => 'Tektronix', 'country' => 'United States'],
            ['name' => 'Rohde & Schwarz', 'country' => 'Germany'],
            ['name' => 'Anritsu', 'country' => 'Japan'],
            ['name' => 'Hioki', 'country' => 'Japan'],
            ['name' => 'Yokogawa', 'country' => 'Japan'],
            ['name' => 'Rigol Technologies', 'country' => 'China'],
            ['name' => 'Siglent Technologies', 'country' => 'China'],
            ['name' => 'Hantek', 'country' => 'China'],
            ['name' => 'OWON', 'country' => 'China'],
            ['name' => 'UNI-T', 'country' => 'China'],
            ['name' => 'Brymen', 'country' => 'Taiwan'],
            ['name' => 'Extech', 'country' => 'United States'],
            ['name' => 'Klein Tools', 'country' => 'United States'],
            ['name' => 'Wera', 'country' => 'Germany'],
            ['name' => 'Wiha', 'country' => 'Germany'],
            ['name' => 'Knipex', 'country' => 'Germany'],
            ['name' => 'Gedore', 'country' => 'Germany'],
            ['name' => 'Hazet', 'country' => 'Germany'],
            ['name' => 'Stahlwille', 'country' => 'Germany'],
            ['name' => 'Facom', 'country' => 'France'],
            ['name' => 'Bahco', 'country' => 'Sweden'],
            ['name' => 'Teng Tools', 'country' => 'Sweden'],
            ['name' => 'Snap-on', 'country' => 'United States'],
            ['name' => 'Irwin', 'country' => 'United States'],
            ['name' => 'Stanley', 'country' => 'United States'],
            ['name' => 'DeWalt', 'country' => 'United States'],
            ['name' => 'Makita', 'country' => 'Japan'],
            ['name' => 'Milwaukee Tool', 'country' => 'United States'],
            ['name' => 'Hilti', 'country' => 'Liechtenstein'],
            ['name' => 'Dremel', 'country' => 'United States'],
            ['name' => 'Weller', 'country' => 'United States'],
            ['name' => 'Hakko', 'country' => 'Japan'],
            ['name' => 'Metcal', 'country' => 'United States'],
            ['name' => 'JBC Soldering', 'country' => 'Spain'],
            ['name' => 'Ersa', 'country' => 'Germany'],
            ['name' => 'ATTEN', 'country' => 'China'],
            ['name' => 'Quick', 'country' => 'China'],
            ['name' => 'Aixun', 'country' => 'China'],

            // ── 3D printing, CNC and robotics ─────────────────────────────
            ['name' => 'Prusa Research', 'country' => 'Czechia'],
            ['name' => 'Creality', 'country' => 'China'],
            ['name' => 'Bambu Lab', 'country' => 'China'],
            ['name' => 'Anycubic', 'country' => 'China'],
            ['name' => 'UltiMaker', 'country' => 'Netherlands'],
            ['name' => 'Formlabs', 'country' => 'United States'],
            ['name' => 'Snapmaker', 'country' => 'China'],
            ['name' => 'FANUC', 'country' => 'Japan'],
            ['name' => 'KUKA', 'country' => 'Germany'],
            ['name' => 'Yaskawa', 'country' => 'Japan'],
            ['name' => 'Universal Robots', 'country' => 'Denmark'],
            ['name' => 'StepperOnline', 'country' => 'China'],
            ['name' => 'Trinamic', 'country' => 'Germany'],
            ['name' => 'Hitec', 'country' => 'South Korea'],
            ['name' => 'Futaba', 'country' => 'Japan'],
            ['name' => 'TowerPro', 'country' => 'China'],
            ['name' => 'Hobbywing', 'country' => 'China'],

            // ── Industrial automation and electrical ──────────────────────
            ['name' => 'ABB', 'country' => 'Switzerland'],
            ['name' => 'Emerson', 'country' => 'United States'],
            ['name' => 'Rockwell Automation', 'country' => 'United States'],
            ['name' => 'Allen-Bradley', 'country' => 'United States'],
            ['name' => 'Mitsubishi Electric', 'country' => 'Japan'],
            ['name' => 'Omron', 'country' => 'Japan'],
            ['name' => 'WAGO', 'country' => 'Germany'],
            ['name' => 'Beckhoff Automation', 'country' => 'Germany'],
            ['name' => 'B&R Industrial Automation', 'country' => 'Austria'],
            ['name' => 'Pilz', 'country' => 'Germany'],
            ['name' => 'SICK', 'country' => 'Germany'],
            ['name' => 'Banner Engineering', 'country' => 'United States'],
            ['name' => 'Keyence', 'country' => 'Japan'],
            ['name' => 'Pepperl+Fuchs', 'country' => 'Germany'],
            ['name' => 'Balluff', 'country' => 'Germany'],
            ['name' => 'ifm electronic', 'country' => 'Germany'],
            ['name' => 'Turck', 'country' => 'Germany'],
            ['name' => 'Carlo Gavazzi', 'country' => 'Switzerland'],
            ['name' => 'Finder', 'country' => 'Italy'],
            ['name' => 'Eaton', 'country' => 'Ireland'],
            ['name' => 'Legrand', 'country' => 'France'],
            ['name' => 'Hager', 'country' => 'Germany'],
            ['name' => 'Autonics', 'country' => 'South Korea'],
            ['name' => 'Fatek', 'country' => 'Taiwan'],
            ['name' => 'Schmersal', 'country' => 'Germany'],
            ['name' => 'Leuze', 'country' => 'Germany'],

            // ── Networking and IT infrastructure ──────────────────────────
            ['name' => 'Ubiquiti', 'country' => 'United States'],
            ['name' => 'TP-Link', 'country' => 'China'],
            ['name' => 'Netgear', 'country' => 'United States'],
            ['name' => 'D-Link', 'country' => 'Taiwan'],
            ['name' => 'MikroTik', 'country' => 'Latvia'],
            ['name' => 'Zyxel', 'country' => 'Taiwan'],
            ['name' => 'Tenda', 'country' => 'China'],
            ['name' => 'Ruijie Networks', 'country' => 'China'],
            ['name' => 'Aruba Networks', 'country' => 'United States'],
            ['name' => 'Juniper Networks', 'country' => 'United States'],
            ['name' => 'Fortinet', 'country' => 'United States'],
            ['name' => 'Palo Alto Networks', 'country' => 'United States'],
            ['name' => 'Extreme Networks', 'country' => 'United States'],
            ['name' => 'Moxa', 'country' => 'Taiwan'],
            ['name' => 'Advantech', 'country' => 'Taiwan'],
            ['name' => 'StarTech.com', 'country' => 'Canada'],
            ['name' => 'Tripp Lite', 'country' => 'United States'],
            ['name' => 'Cable Matters', 'country' => 'United States'],
            ['name' => 'Monoprice', 'country' => 'United States'],
            ['name' => 'Anker', 'country' => 'China'],
            ['name' => 'Ugreen', 'country' => 'China'],
            ['name' => 'Baseus', 'country' => 'China'],
            ['name' => 'Belkin', 'country' => 'United States'],
            ['name' => 'Orico', 'country' => 'China'],

            // ── PC components, peripherals and displays ───────────────────
            ['name' => 'ASUS', 'country' => 'Taiwan'],
            ['name' => 'Acer', 'country' => 'Taiwan'],
            ['name' => 'ASRock', 'country' => 'Taiwan'],
            ['name' => 'Gigabyte', 'country' => 'Taiwan'],
            ['name' => 'MSI', 'country' => 'Taiwan'],
            ['name' => 'Corsair', 'country' => 'United States'],
            ['name' => 'Kingston Technology', 'country' => 'United States'],
            ['name' => 'Crucial', 'country' => 'United States'],
            ['name' => 'Western Digital', 'country' => 'United States'],
            ['name' => 'Seagate', 'country' => 'United States'],
            ['name' => 'SanDisk', 'country' => 'United States'],
            ['name' => 'Transcend', 'country' => 'Taiwan'],
            ['name' => 'ADATA', 'country' => 'Taiwan'],
            ['name' => 'TeamGroup', 'country' => 'Taiwan'],
            ['name' => 'G.Skill', 'country' => 'Taiwan'],
            ['name' => 'Seasonic', 'country' => 'Taiwan'],
            ['name' => 'FSP Group', 'country' => 'Taiwan'],
            ['name' => 'Cooler Master', 'country' => 'Taiwan'],
            ['name' => 'NZXT', 'country' => 'United States'],
            ['name' => 'Lian Li', 'country' => 'Taiwan'],
            ['name' => 'Noctua', 'country' => 'Austria'],
            ['name' => 'ARCTIC', 'country' => 'Germany'],
            ['name' => 'Thermalright', 'country' => 'Taiwan'],
            ['name' => 'be quiet!', 'country' => 'Germany'],
            ['name' => 'Phanteks', 'country' => 'Netherlands'],
            ['name' => 'Fractal Design', 'country' => 'Sweden'],
            ['name' => 'Logitech', 'country' => 'Switzerland'],
            ['name' => 'Razer', 'country' => 'Singapore'],
            ['name' => 'SteelSeries', 'country' => 'Denmark'],
            ['name' => 'HyperX', 'country' => 'United States'],
            ['name' => 'Keychron', 'country' => 'China'],
            ['name' => 'Ducky', 'country' => 'Taiwan'],
            ['name' => 'Redragon', 'country' => 'China'],
            ['name' => 'BenQ', 'country' => 'Taiwan'],
            ['name' => 'AOC', 'country' => 'Taiwan'],
            ['name' => 'ViewSonic', 'country' => 'United States'],
            ['name' => 'EIZO', 'country' => 'Japan'],
            ['name' => 'Elgato', 'country' => 'Germany'],
            ['name' => 'AVerMedia', 'country' => 'Taiwan'],

            // ── Print, scan and barcode ───────────────────────────────────
            ['name' => 'Toshiba', 'country' => 'Japan'],
            ['name' => 'Epson', 'country' => 'Japan'],
            ['name' => 'Brother', 'country' => 'Japan'],
            ['name' => 'Xerox', 'country' => 'United States'],
            ['name' => 'Ricoh', 'country' => 'Japan'],
            ['name' => 'Fujitsu', 'country' => 'Japan'],
            ['name' => 'Kyocera', 'country' => 'Japan'],
            ['name' => 'Zebra Technologies', 'country' => 'United States'],
            ['name' => 'Datalogic', 'country' => 'Italy'],

            // ── Audio, video and security ─────────────────────────────────
            ['name' => 'Sennheiser', 'country' => 'Germany'],
            ['name' => 'Audio-Technica', 'country' => 'Japan'],
            ['name' => 'Shure', 'country' => 'United States'],
            ['name' => 'RODE', 'country' => 'Australia'],
            ['name' => 'JBL', 'country' => 'United States'],
            ['name' => 'Bose', 'country' => 'United States'],
            ['name' => 'Focusrite', 'country' => 'United Kingdom'],
            ['name' => 'Behringer', 'country' => 'Germany'],
            ['name' => 'Jabra', 'country' => 'Denmark'],
            ['name' => 'Poly', 'country' => 'United States'],
            ['name' => 'Yealink', 'country' => 'China'],
            ['name' => 'Grandstream', 'country' => 'United States'],
            ['name' => 'Hikvision', 'country' => 'China'],
            ['name' => 'Dahua Technology', 'country' => 'China'],
            ['name' => 'Axis Communications', 'country' => 'Sweden'],

            // ── Drones, smart home and safety ─────────────────────────────
            ['name' => 'DJI', 'country' => 'China'],
            ['name' => 'Autel Robotics', 'country' => 'China'],
            ['name' => 'Shelly', 'country' => 'Bulgaria'],
            ['name' => 'Sonoff', 'country' => 'China'],
            ['name' => 'Aqara', 'country' => 'China'],
            ['name' => 'Tuya', 'country' => 'China'],
            ['name' => 'Eve Systems', 'country' => 'Germany'],
            ['name' => 'Ecobee', 'country' => 'Canada'],
            ['name' => 'MSA Safety', 'country' => 'United States'],
            ['name' => 'Ansell', 'country' => 'Australia'],
            ['name' => 'uvex', 'country' => 'Germany'],
            ['name' => 'Portwest', 'country' => 'Ireland'],
        ];
    }
}
