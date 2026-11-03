<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Support\Catalog\CategoryCache;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $this->migrateLegacyRoot();

        $categories = $this->categories();
        $reservedCodes = $this->explicitCodes($categories);

        Category::withoutEvents(function () use ($categories, $reservedCodes) {
            foreach ($categories as $data) {
                $this->seedCategory($data, null, $reservedCodes);
            }
        });

        CategoryCache::flush();

        $this->command->info('Product categories seeded.');
    }

    /**
     * The catalog split renamed the legacy "IT & Electronics" root to
     * Electronics. Rename it in place so seeding an existing database updates
     * that row (and re-parents its children) instead of creating a duplicate
     * root next to it.
     */
    protected function migrateLegacyRoot(): void
    {
        $legacy = Category::withTrashed()->where('slug', 'it-electronics')->first();

        if (! $legacy) {
            return;
        }

        if (Category::withTrashed()->where('slug', 'electronics')->exists()) {
            return;
        }

        $legacy->update(['slug' => 'electronics', 'name' => 'Electronics']);
    }

    protected function seedCategory(array $data, ?Category $parent = null, array $reservedCodes = []): void
    {
        $children = $data['children'] ?? [];
        unset($data['children']);

        $data['parent_id'] = $parent?->id;

        // Find soft-deleted rows too, so a category that was pruned (or deleted
        // by an operator) is restored instead of tripping the unique slug index.
        $category = Category::withTrashed()->firstOrNew(['slug' => $data['slug']]);
        $isNew = ! $category->exists;
        $wasTrashed = $category->trashed();

        // `is_active` is operational state, not seed-defined content: preserve an
        // operator's choice on re-seed unless the seed file sets it explicitly.
        if (array_key_exists('is_active', $data)) {
            // Explicit seed value wins.
        } elseif ($isNew || $wasTrashed) {
            $data['is_active'] = true;
        } else {
            unset($data['is_active']);
        }

        if (! empty($data['code'])) {
            // Explicit codes always win. If an auto-generated code from a previous
            // run was assigned to the wrong category, take it back and re-assign.
            $data['code'] = $this->claimExplicitCode($data['code'], $data['slug'], $reservedCodes);
        } elseif ($isNew) {
            // Codes are department-level integration keys. Children stay un-coded
            // unless the seed file (or an admin) assigns one, so fresh seeds are
            // deterministic instead of inventing AA/AB fallbacks from DB state.
            $data['code'] = null;
        }

        $category->fill($data);

        if ($wasTrashed) {
            $category->deleted_at = null;
        }

        $category->save();

        // The seeder runs inside withoutEvents(), so the model's level/path
        // hook never fires. Fill them explicitly to keep hierarchy-aware
        // queries (level scopes, path lookups) correct after a fresh seed.
        $level = $parent ? $parent->level + 1 : 0;
        $path = ($parent?->path ?? '/').$category->id.'/';

        if ($category->level !== $level || $category->path !== $path) {
            $category->forceFill(['level' => $level, 'path' => $path])->saveQuietly();
        }

        foreach ($children as $child) {
            $this->seedCategory($child, $category, $reservedCodes);
        }
    }

    /**
     * All explicit codes from the seed data — auto-generated codes must never take these.
     */
    protected function explicitCodes(array $categories, array &$codes = []): array
    {
        foreach ($categories as $data) {
            if (! empty($data['code'])) {
                $codes[] = strtoupper($data['code']);
            }
            if (! empty($data['children'])) {
                $this->explicitCodes($data['children'], $codes);
            }
        }

        return array_values(array_unique($codes));
    }

    /**
     * Reclaim an explicit code currently held by another (auto-generated) category.
     */
    protected function claimExplicitCode(string $code, string $slug, array $reservedCodes): string
    {
        $code = strtoupper($code);

        $holder = Category::withTrashed()
            ->where('code', $code)
            ->where('slug', '!=', $slug)
            ->first();

        if ($holder) {
            $holder->update([
                'code' => $this->resolveCategoryCode($holder->name, array_merge($reservedCodes, [$code])),
            ]);
        }

        return $code;
    }

    /**
     * Deterministic 2-char code: first letters of the name when free, otherwise
     * the first unused AA..ZZ combination.
     */
    protected function resolveCategoryCode(string $name, array $reservedCodes = []): string
    {
        $used = Category::withTrashed()
            ->pluck('code')
            ->filter()
            ->map(fn ($code) => strtoupper((string) $code))
            ->flip();

        foreach ($reservedCodes as $code) {
            $used->put(strtoupper($code), true);
        }

        $base = strtoupper(substr(Str::slug($name), 0, 2)) ?: 'ZZ';
        if (! $used->has($base)) {
            return $base;
        }

        for ($i = 0; $i < 676; $i++) {
            $candidate = chr(65 + intdiv($i, 26)).chr(65 + ($i % 26));
            if (! $used->has($candidate)) {
                return $candidate;
            }
        }

        return $base;
    }

    /**
     * Every slug this seeder manages. Used by `catalog:prune-categories` so
     * operators can remove rows the seed file no longer defines.
     *
     * @return array<int, string>
     */
    public static function seededSlugs(): array
    {
        $seeder = new self;
        $slugs = [];

        $walk = function (array $categories) use (&$walk, &$slugs): void {
            foreach ($categories as $category) {
                $slugs[] = $category['slug'];

                if (! empty($category['children'])) {
                    $walk($category['children']);
                }
            }
        };

        $walk($seeder->categories());

        return $slugs;
    }

    private function categories(): array
    {
        return [

            // ══════════════════════════════════════════
            //  1. ELECTRONICS
            // ══════════════════════════════════════════
            [
                'name' => 'Electronics',
                'code' => 'EC',
                'slug' => 'electronics',
                'description' => 'Semiconductors, passive and electromechanical components, development boards, sensors, test equipment, and PCB supplies.',
                'sort_order' => 1,
                'children' => [
                    ['name' => 'Development Boards & Kits', 'slug' => 'development-boards-kits', 'sort_order' => 1],
                    [
                        'name' => 'Electronic Components',
                        'code' => 'CM',
                        'slug' => 'electronic-components',
                        'sort_order' => 2,
                        'children' => [
                            ['name' => 'Semiconductors', 'slug' => 'semiconductors', 'sort_order' => 1],
                            ['name' => 'Passive Components', 'slug' => 'passive-components', 'sort_order' => 2],
                            ['name' => 'Electromechanical Components', 'slug' => 'electromechanical-components', 'sort_order' => 3],
                            ['name' => 'Circuit Protection', 'slug' => 'circuit-protection', 'sort_order' => 4],
                        ],
                    ],
                    ['name' => 'Connectors & Terminals', 'slug' => 'connectors-terminals', 'sort_order' => 3],
                    ['name' => 'Sensors & Transducers', 'slug' => 'sensors-transducers', 'sort_order' => 4],
                    ['name' => 'Optoelectronics & Displays', 'slug' => 'optoelectronics-displays', 'sort_order' => 5],
                    ['name' => 'Power Supplies & Converters', 'slug' => 'power-supplies-converters', 'sort_order' => 6],
                    ['name' => 'Test & Measurement', 'slug' => 'test-measurement', 'sort_order' => 7],
                    ['name' => 'PCB & Prototyping', 'slug' => 'pcbs-prototyping', 'sort_order' => 8],
                    ['name' => 'Soldering & Rework', 'slug' => 'soldering-rework', 'sort_order' => 9],
                ],
            ],

            // ══════════════════════════════════════════
            //  2. INFORMATION TECHNOLOGY
            // ══════════════════════════════════════════
            [
                'name' => 'Information Technology',
                'code' => 'IT',
                'slug' => 'information-technology',
                'description' => 'Computers, servers, networking, software, peripherals, security, and end-user devices.',
                'sort_order' => 2,
                'children' => [
                    ['name' => 'Laptops & Notebooks', 'slug' => 'laptops-notebooks', 'sort_order' => 1],
                    ['name' => 'Desktop Computers', 'slug' => 'desktop-computers', 'sort_order' => 2],
                    ['name' => 'Workstations & Thin Clients', 'slug' => 'workstations-thin-clients', 'sort_order' => 3],
                    ['name' => 'Monitors & Displays', 'slug' => 'monitors-displays', 'sort_order' => 4],
                    ['name' => 'Tablets & e-Readers', 'slug' => 'tablets-ereaders', 'sort_order' => 5],
                    ['name' => 'Mobile Phones & Accessories', 'slug' => 'mobile-phones-accessories', 'sort_order' => 6],
                    ['name' => 'Printers, Scanners & Copiers', 'slug' => 'printers-scanners-copiers', 'sort_order' => 7],
                    ['name' => 'Ink, Toner & Print Supplies', 'slug' => 'ink-toner-print-supplies', 'sort_order' => 8],
                    ['name' => 'Servers & Storage', 'slug' => 'servers-storage', 'sort_order' => 9],
                    ['name' => 'Server Racks & KVMs', 'slug' => 'server-racks-kvms', 'sort_order' => 10],
                    ['name' => 'Networking Equipment', 'slug' => 'networking-equipment', 'sort_order' => 11],
                    ['name' => 'Security & Surveillance', 'slug' => 'security-surveillance', 'sort_order' => 12],
                    ['name' => 'Computer Components', 'slug' => 'computer-components', 'sort_order' => 13],
                    ['name' => 'Computer Accessories', 'slug' => 'computer-accessories', 'sort_order' => 14],
                    ['name' => 'Docking Stations & Hubs', 'slug' => 'docking-stations-hubs', 'sort_order' => 15],
                    ['name' => 'Storage Media & Drives', 'slug' => 'storage-media-drives', 'sort_order' => 16],
                    ['name' => 'Software & Licenses', 'slug' => 'software-licenses', 'sort_order' => 17],
                    ['name' => 'Computer Cables & Adapters', 'slug' => 'cables-adapters', 'sort_order' => 18],
                    ['name' => 'UPS & Power Protection', 'slug' => 'power-ups', 'sort_order' => 19],
                    ['name' => 'Headsets & Conference Audio', 'slug' => 'audio-headsets', 'sort_order' => 20],
                    ['name' => 'Webcams & Video Conferencing', 'slug' => 'webcams-video-conferencing', 'sort_order' => 21],
                    ['name' => 'Projectors & AV Equipment', 'slug' => 'projectors-av-equipment', 'sort_order' => 22],
                    ['name' => 'Barcode, POS & Retail Hardware', 'slug' => 'barcode-pos-retail', 'sort_order' => 23],
                    ['name' => 'Cameras & Imaging', 'slug' => 'cameras-imaging', 'sort_order' => 24],
                    ['name' => 'IoT & Smart Devices', 'slug' => 'iot-smart-devices', 'sort_order' => 25],
                ],
            ],

            // ══════════════════════════════════════════
            //  3. OFFICE SUPPLIES
            // ══════════════════════════════════════════
            [
                'name' => 'Office Supplies',
                'code' => 'OS',
                'slug' => 'office-supplies',
                'description' => 'Paper, writing instruments, filing, office machines, and daily office consumables.',
                'sort_order' => 3,
                'children' => [
                    ['name' => 'Copy & Printer Paper', 'slug' => 'paper-copy-paper', 'sort_order' => 1],
                    ['name' => 'Notebooks & Pads', 'slug' => 'notebooks-pads', 'sort_order' => 2],
                    ['name' => 'Writing Instruments', 'slug' => 'writing-instruments', 'sort_order' => 3],
                    ['name' => 'Markers & Highlighters', 'slug' => 'markers-highlighters', 'sort_order' => 4],
                    ['name' => 'Desk Organizers & Accessories', 'slug' => 'desk-organizers-accessories', 'sort_order' => 5],
                    ['name' => 'Filing Supplies', 'slug' => 'filing-supplies', 'sort_order' => 6],
                    ['name' => 'Folders & Binders', 'slug' => 'folders-binders', 'sort_order' => 7],
                    ['name' => 'Staplers & Punches', 'slug' => 'staplers-punches', 'sort_order' => 8],
                    ['name' => 'Office Tape & Adhesives', 'slug' => 'tape-glue-adhesives', 'sort_order' => 9],
                    ['name' => 'Office Envelopes & Mailers', 'slug' => 'envelopes-mailers', 'sort_order' => 10],
                    ['name' => 'Calendars & Planners', 'slug' => 'calendars-planners', 'sort_order' => 11],
                    ['name' => 'Presentation Supplies', 'slug' => 'presentation-supplies', 'sort_order' => 12],
                    ['name' => 'Whiteboards & Bulletin Boards', 'slug' => 'whiteboards-bulletin-boards', 'sort_order' => 13],
                    ['name' => 'Stamps & Ink Pads', 'slug' => 'stamps-ink-pads', 'sort_order' => 14],
                    ['name' => 'Calculators', 'slug' => 'calculators', 'sort_order' => 15],
                    ['name' => 'Office Machines', 'slug' => 'office-machines', 'sort_order' => 16],
                    ['name' => 'Cash Handling & Safes', 'slug' => 'cash-handling-safes', 'sort_order' => 17],
                    ['name' => 'Meeting Room Supplies', 'slug' => 'meeting-room-supplies', 'sort_order' => 18],
                ],
            ],

            // ══════════════════════════════════════════
            //  4. FURNITURE
            // ══════════════════════════════════════════
            [
                'name' => 'Furniture',
                'code' => 'FU',
                'slug' => 'furniture',
                'description' => 'Office, workplace, and institutional furniture.',
                'sort_order' => 4,
                'children' => [
                    ['name' => 'Office Chairs & Seating', 'slug' => 'office-chairs-seating', 'sort_order' => 1],
                    ['name' => 'Ergonomic Chairs', 'slug' => 'ergonomic-chairs', 'sort_order' => 2],
                    ['name' => 'Desks & Workstations', 'slug' => 'desks-workstations', 'sort_order' => 3],
                    ['name' => 'Standing Desks', 'slug' => 'standing-desks', 'sort_order' => 4],
                    ['name' => 'Cubicles & Workstations', 'slug' => 'cubicles-workstations', 'sort_order' => 5],
                    ['name' => 'Filing Cabinets & Pedestals', 'slug' => 'filing-cabinets-pedestals', 'sort_order' => 6],
                    ['name' => 'Storage Cabinets & Shelving', 'slug' => 'storage-cabinets-shelving', 'sort_order' => 7],
                    ['name' => 'Bookcases', 'slug' => 'bookcases', 'sort_order' => 8],
                    ['name' => 'Meeting & Conference Tables', 'slug' => 'meeting-conference-tables', 'sort_order' => 9],
                    ['name' => 'Reception Furniture', 'slug' => 'reception-furniture', 'sort_order' => 10],
                    ['name' => 'Breakroom Furniture', 'slug' => 'breakroom-furniture', 'sort_order' => 11],
                    ['name' => 'Outdoor Furniture', 'slug' => 'outdoor-furniture', 'sort_order' => 12],
                    ['name' => 'Office Carts & Trolleys', 'slug' => 'carts-trolleys', 'sort_order' => 13],
                    ['name' => 'Partitions & Panels', 'slug' => 'partitions-panels', 'sort_order' => 14],
                    ['name' => 'Furniture Parts & Accessories', 'slug' => 'furniture-parts-accessories', 'sort_order' => 15],
                ],
            ],

            // ══════════════════════════════════════════
            //  5. MRO & INDUSTRIAL
            // ══════════════════════════════════════════
            [
                'name' => 'MRO & Industrial',
                'code' => 'MR',
                'slug' => 'mro-industrial',
                'description' => 'Maintenance, repair, and operations: tools, fasteners, material handling, fluid power, and industrial supplies.',
                'sort_order' => 5,
                'children' => [
                    ['name' => 'Hand Tools', 'slug' => 'hand-tools', 'sort_order' => 1],
                    ['name' => 'Power Tools', 'slug' => 'power-tools', 'sort_order' => 2],
                    ['name' => 'Tool Storage', 'slug' => 'tool-storage', 'sort_order' => 3],
                    ['name' => 'Measuring & Layout', 'slug' => 'measuring-layout-tools', 'sort_order' => 4],
                    ['name' => 'Abrasives & Grinding', 'slug' => 'abrasives-grinding', 'sort_order' => 5],
                    ['name' => 'Cutting Tools', 'slug' => 'cutting-tools', 'sort_order' => 6],
                    ['name' => 'Machining & Tooling', 'slug' => 'machining-tooling', 'sort_order' => 7],
                    ['name' => 'Fasteners & Industrial Hardware', 'slug' => 'fasteners-hardware', 'sort_order' => 8],
                    ['name' => 'Industrial Adhesives & Sealants', 'slug' => 'industrial-adhesives-sealants', 'sort_order' => 9],
                    ['name' => 'Seals, O-Rings & Gaskets', 'slug' => 'seals-o-rings-gaskets', 'sort_order' => 10],
                    [
                        'name' => 'Material Handling',
                        'slug' => 'material-handling',
                        'sort_order' => 11,
                        'children' => [
                            ['name' => 'Carts & Trucks', 'slug' => 'carts-trucks', 'sort_order' => 1],
                            ['name' => 'Pallet Trucks & Lifts', 'slug' => 'pallet-trucks-lifts', 'sort_order' => 2],
                            ['name' => 'Racking & Shelving', 'slug' => 'racking-shelving', 'sort_order' => 3],
                            ['name' => 'Conveyors & Sortation', 'slug' => 'conveyors-sortation', 'sort_order' => 4],
                            ['name' => 'Lifting & Rigging', 'slug' => 'lifting-rigging', 'sort_order' => 5],
                            ['name' => 'Storage Bins & Containers', 'slug' => 'storage-bins-containers', 'sort_order' => 6],
                        ],
                    ],
                    ['name' => 'Pumps & Motors', 'slug' => 'pumps-motors', 'sort_order' => 12],
                    ['name' => 'Compressors & Pneumatics', 'slug' => 'compressors-pneumatics', 'sort_order' => 13],
                    ['name' => 'Hydraulics', 'slug' => 'hydraulics', 'sort_order' => 14],
                    ['name' => 'Bearings & Power Transmission', 'slug' => 'bearings-power-transmission', 'sort_order' => 15],
                    [
                        'name' => 'Welding Equipment',
                        'slug' => 'welding-equipment',
                        'sort_order' => 16,
                        'children' => [
                            ['name' => 'Welders & Cutters', 'slug' => 'welders-cutters', 'sort_order' => 1],
                            ['name' => 'Filler Metals & Consumables', 'slug' => 'welding-consumables', 'sort_order' => 2],
                            ['name' => 'Welding PPE', 'slug' => 'welding-ppe', 'sort_order' => 3],
                        ],
                    ],
                    ['name' => 'Ladders & Scaffolding', 'slug' => 'ladders-scaffolding', 'sort_order' => 17],
                    ['name' => 'Lubrication Equipment', 'slug' => 'lubrication-equipment', 'sort_order' => 18],
                ],
            ],

            // ══════════════════════════════════════════
            //  6. SAFETY & PPE
            // ══════════════════════════════════════════
            [
                'name' => 'Safety & PPE',
                'code' => 'SP',
                'slug' => 'safety-ppe',
                'description' => 'Personal protective equipment, gas detection, and workplace safety supplies.',
                'sort_order' => 6,
                'children' => [
                    ['name' => 'Eye & Face Protection', 'slug' => 'eye-face-protection', 'sort_order' => 1],
                    ['name' => 'Safety Gloves', 'slug' => 'hand-protection', 'sort_order' => 2],
                    ['name' => 'Head Protection', 'slug' => 'head-protection', 'sort_order' => 3],
                    ['name' => 'Hearing Protection', 'slug' => 'hearing-protection', 'sort_order' => 4],
                    ['name' => 'Respiratory Protection', 'slug' => 'respiratory-protection', 'sort_order' => 5],
                    ['name' => 'Foot Protection', 'slug' => 'foot-protection', 'sort_order' => 6],
                    ['name' => 'High-Visibility & FR Clothing', 'slug' => 'protective-clothing', 'sort_order' => 7],
                    ['name' => 'Fall Protection', 'slug' => 'fall-protection', 'sort_order' => 8],
                    ['name' => 'Fire Safety', 'slug' => 'fire-safety', 'sort_order' => 9],
                    ['name' => 'First Aid Kits & Stations', 'slug' => 'first-aid-emergency', 'sort_order' => 10],
                    ['name' => 'Safety Signs & Labels', 'slug' => 'safety-signs-labels', 'sort_order' => 11],
                    ['name' => 'Traffic & Parking Safety', 'slug' => 'traffic-parking-safety', 'sort_order' => 12],
                    ['name' => 'Spill Control', 'slug' => 'spill-control', 'sort_order' => 13],
                    ['name' => 'Lockout/Tagout (LOTO)', 'slug' => 'lockout-tagout-loto', 'sort_order' => 14],
                    ['name' => 'Gas Detection & Monitoring', 'slug' => 'gas-detection-monitoring', 'sort_order' => 15],
                    ['name' => 'Emergency Showers & Eyewash', 'slug' => 'emergency-showers-eyewash', 'sort_order' => 16],
                    ['name' => 'Floor Marking & Barriers', 'slug' => 'floor-marking-barriers', 'sort_order' => 17],
                    ['name' => 'Ergonomic Supports', 'slug' => 'ergonomic-supports', 'sort_order' => 18],
                    ['name' => 'Electrical Safety & Arc Flash', 'slug' => 'electrical-safety-arc-flash', 'sort_order' => 19],
                    ['name' => 'PPE Dispensers & Storage', 'slug' => 'ppe-dispensers-storage', 'sort_order' => 20],
                ],
            ],

            // ══════════════════════════════════════════
            //  7. CLEANING & JANITORIAL
            // ══════════════════════════════════════════
            [
                'name' => 'Cleaning & Janitorial',
                'code' => 'CJ',
                'slug' => 'cleaning-janitorial',
                'description' => 'Janitorial chemicals, paper, floor care, and sanitary supplies.',
                'sort_order' => 7,
                'children' => [
                    ['name' => 'Janitorial Chemicals & Cleaners', 'slug' => 'cleaning-chemicals', 'sort_order' => 1],
                    ['name' => 'Disinfectants & Sanitizers', 'slug' => 'disinfectants-sanitizers', 'sort_order' => 2],
                    ['name' => 'Tissue, Napkins & Dispensers', 'slug' => 'paper-products-dispensers', 'sort_order' => 3],
                    ['name' => 'Cleaning Tools & Mops', 'slug' => 'cleaning-tools-mops', 'sort_order' => 4],
                    ['name' => 'Brooms, Brushes & Dustpans', 'slug' => 'brooms-brushes-dustpans', 'sort_order' => 5],
                    ['name' => 'Trash Bags & Liners', 'slug' => 'trash-bags-liners', 'sort_order' => 6],
                    ['name' => 'Waste & Recycling Bins', 'slug' => 'waste-recycling-bins', 'sort_order' => 7],
                    ['name' => 'Vacuum Cleaners & Floor Care', 'slug' => 'vacuum-cleaners-floor-care', 'sort_order' => 8],
                    ['name' => 'Floor Machines & Scrubbers', 'slug' => 'floor-machines-scrubbers', 'sort_order' => 9],
                    ['name' => 'Mats & Matting', 'slug' => 'mats-matting', 'sort_order' => 10],
                    ['name' => 'Pressure Washers', 'slug' => 'pressure-washers', 'sort_order' => 11],
                    ['name' => 'Restroom Accessories & Supplies', 'slug' => 'restroom-supplies', 'sort_order' => 12],
                    ['name' => 'Air Fresheners & Odor Control', 'slug' => 'air-fresheners-odor-control', 'sort_order' => 13],
                    ['name' => 'Laundry Supplies', 'slug' => 'laundry-supplies', 'sort_order' => 14],
                ],
            ],

            // ══════════════════════════════════════════
            //  8. PACKAGING & SHIPPING
            // ══════════════════════════════════════════
            [
                'name' => 'Packaging & Shipping',
                'code' => 'PS',
                'slug' => 'packaging-shipping',
                'description' => 'Packaging materials, shipping supplies, and mailroom equipment.',
                'sort_order' => 8,
                'children' => [
                    ['name' => 'Boxes & Cartons', 'slug' => 'boxes-cartons', 'sort_order' => 1],
                    ['name' => 'Shipping Mailers & Envelopes', 'slug' => 'padded-mailers-envelopes', 'sort_order' => 2],
                    ['name' => 'Poly Bags & Sleeves', 'slug' => 'poly-bags-sleeves', 'sort_order' => 3],
                    ['name' => 'Packing Tape & Dispensers', 'slug' => 'packing-tape-dispensers', 'sort_order' => 4],
                    ['name' => 'Bubble Wrap & Cushioning', 'slug' => 'bubble-wrap-cushioning', 'sort_order' => 5],
                    ['name' => 'Packing Peanuts & Foam', 'slug' => 'packing-peanuts-foam', 'sort_order' => 6],
                    ['name' => 'Protective Packaging & Edge Protectors', 'slug' => 'protective-packaging-edge-protectors', 'sort_order' => 7],
                    ['name' => 'Stretch & Shrink Wrap', 'slug' => 'stretch-shrink-wrap', 'sort_order' => 8],
                    ['name' => 'Strapping & Banding', 'slug' => 'strapping-banding', 'sort_order' => 9],
                    ['name' => 'Strapping Tools & Equipment', 'slug' => 'strapping-tools-equipment', 'sort_order' => 10],
                    ['name' => 'Labels, Ribbons & Label Printers', 'slug' => 'shipping-labels-printers', 'sort_order' => 11],
                    ['name' => 'Scales & Mailroom Equipment', 'slug' => 'postal-scales-meters', 'sort_order' => 12],
                    ['name' => 'Pallets & Dunnage', 'slug' => 'pallets-dunnage', 'sort_order' => 13],
                ],
            ],

            // ══════════════════════════════════════════
            //  9. LAB & SCIENTIFIC
            // ══════════════════════════════════════════
            [
                'name' => 'Lab & Scientific',
                'code' => 'LB',
                'slug' => 'lab-scientific',
                'description' => 'Laboratory equipment, instruments, consumables, furniture, and chemicals.',
                'sort_order' => 9,
                'children' => [
                    ['name' => 'Lab Equipment & Instruments', 'slug' => 'lab-equipment-instruments', 'sort_order' => 1],
                    ['name' => 'Lab Furniture & Fume Hoods', 'slug' => 'lab-furniture-fume-hoods', 'sort_order' => 2],
                    ['name' => 'Glassware & Plasticware', 'slug' => 'glassware-plasticware', 'sort_order' => 3],
                    ['name' => 'Lab Consumables', 'slug' => 'lab-consumables', 'sort_order' => 4],
                    ['name' => 'Chemicals & Reagents', 'slug' => 'chemicals-reagents', 'sort_order' => 5],
                    ['name' => 'Lab Test & Measurement', 'slug' => 'test-measurement-inspection', 'sort_order' => 6],
                    ['name' => 'Microscopes & Optics', 'slug' => 'microscopes-optics', 'sort_order' => 7],
                    ['name' => 'Centrifuges & Mixers', 'slug' => 'centrifuges-mixers', 'sort_order' => 8],
                    ['name' => 'Pipettes & Liquid Handling', 'slug' => 'pipettes-liquid-handling', 'sort_order' => 9],
                    ['name' => 'Balances & Scales', 'slug' => 'balances-scales', 'sort_order' => 10],
                    ['name' => 'Cold Storage & Freezers', 'slug' => 'cold-storage-freezers', 'sort_order' => 11],
                    ['name' => 'Water Purification Systems', 'slug' => 'water-purification-systems', 'sort_order' => 12],
                    ['name' => 'Lab Safety & PPE', 'slug' => 'lab-safety-ppe', 'sort_order' => 13],
                    ['name' => 'Chromatography Supplies', 'slug' => 'chromatography-supplies', 'sort_order' => 14],
                    ['name' => 'Gas Cylinders & Regulators', 'slug' => 'gas-cylinders-regulators', 'sort_order' => 15],
                ],
            ],

            // ══════════════════════════════════════════
            // 10. MEDICAL & HEALTHCARE
            // ══════════════════════════════════════════
            [
                'name' => 'Medical & Healthcare',
                'code' => 'MD',
                'slug' => 'medical-healthcare',
                'description' => 'Medical supplies, equipment, infection control, and healthcare products.',
                'sort_order' => 10,
                'children' => [
                    ['name' => 'Medical Equipment', 'slug' => 'medical-equipment', 'sort_order' => 1],
                    ['name' => 'Diagnostic Instruments', 'slug' => 'diagnostic-instruments', 'sort_order' => 2],
                    ['name' => 'Surgical Supplies', 'slug' => 'surgical-supplies', 'sort_order' => 3],
                    ['name' => 'Syringes & Needles', 'slug' => 'syringes-needles', 'sort_order' => 4],
                    ['name' => 'Bandages & Wound Care', 'slug' => 'bandages-wound-care', 'sort_order' => 5],
                    ['name' => 'Medical Gloves', 'slug' => 'gloves-medical', 'sort_order' => 6],
                    ['name' => 'Masks & Face Shields', 'slug' => 'masks-face-shields', 'sort_order' => 7],
                    ['name' => 'Gowns & Aprons', 'slug' => 'gowns-aprons', 'sort_order' => 8],
                    ['name' => 'Patient Care & Mobility', 'slug' => 'patient-care-mobility', 'sort_order' => 9],
                    ['name' => 'Medical Furniture & Beds', 'slug' => 'medical-furniture-beds', 'sort_order' => 10],
                    ['name' => 'Infection Control & Sterilization', 'slug' => 'infection-control-sterilization', 'sort_order' => 11],
                    ['name' => 'IV & Infusion Supplies', 'slug' => 'iv-infusion-supplies', 'sort_order' => 12],
                    ['name' => 'Respiratory & Oxygen Therapy', 'slug' => 'respiratory-oxygen-therapy', 'sort_order' => 13],
                    ['name' => 'Pharmaceuticals & OTC', 'slug' => 'pharmaceuticals-otc', 'sort_order' => 14],
                    ['name' => 'Dental Supplies', 'slug' => 'dental-supplies', 'sort_order' => 15],
                    ['name' => 'Veterinary Supplies', 'slug' => 'veterinary-supplies', 'sort_order' => 16],
                    ['name' => 'Home Health & Daily Living', 'slug' => 'home-health-daily-living', 'sort_order' => 17],
                ],
            ],

            // ══════════════════════════════════════════
            // 11. FOOD SERVICE & BREAKROOM
            // ══════════════════════════════════════════
            [
                'name' => 'Food Service & Breakroom',
                'code' => 'FB',
                'slug' => 'food-beverage',
                'description' => 'Coffee, water, snacks, breakroom supplies, and foodservice essentials.',
                'sort_order' => 11,
                'children' => [
                    ['name' => 'Coffee & Tea', 'slug' => 'coffee-tea', 'sort_order' => 1],
                    ['name' => 'Water & Beverages', 'slug' => 'water-beverages', 'sort_order' => 2],
                    ['name' => 'Snacks & Confectionery', 'slug' => 'snacks-confectionery', 'sort_order' => 3],
                    ['name' => 'Condiments & Sweeteners', 'slug' => 'condiments-sweeteners', 'sort_order' => 4],
                    ['name' => 'Breakroom Supplies', 'slug' => 'breakroom-supplies', 'sort_order' => 5],
                    ['name' => 'Disposable Cups, Plates & Utensils', 'slug' => 'disposable-cups-utensils', 'sort_order' => 6],
                    ['name' => 'Food Storage Containers', 'slug' => 'food-storage-containers', 'sort_order' => 7],
                    ['name' => 'Catering & Foodservice Equipment', 'slug' => 'catering-equipment', 'sort_order' => 8],
                    ['name' => 'Vending Machine Supplies', 'slug' => 'vending-machine-supplies', 'sort_order' => 9],
                ],
            ],

            // ══════════════════════════════════════════
            // 12. ELECTRICAL & LIGHTING
            // ══════════════════════════════════════════
            [
                'name' => 'Electrical & Lighting',
                'code' => 'EL',
                'slug' => 'electrical-lighting',
                'description' => 'Wiring, circuit protection, power distribution, lighting fixtures, and electrical test equipment.',
                'sort_order' => 12,
                'children' => [
                    ['name' => 'Building Wire & Cable', 'slug' => 'wires-cables', 'sort_order' => 1],
                    ['name' => 'Switches & Outlets', 'slug' => 'switches-outlets', 'sort_order' => 2],
                    ['name' => 'Circuit Breakers & Panels', 'slug' => 'circuit-breakers-panels', 'sort_order' => 3],
                    ['name' => 'Fuses & Circuit Protection', 'slug' => 'fuses-circuit-protection', 'sort_order' => 4],
                    ['name' => 'Contactors, Relays & Starters', 'slug' => 'contactors-relays-starters', 'sort_order' => 5],
                    ['name' => 'Transformers & Power Distribution', 'slug' => 'transformers-power-distribution', 'sort_order' => 6],
                    ['name' => 'Conduit & Fittings', 'slug' => 'conduit-fittings', 'sort_order' => 7],
                    ['name' => 'Electrical Boxes & Enclosures', 'slug' => 'electrical-boxes-enclosures', 'sort_order' => 8],
                    ['name' => 'Terminal Blocks & Wire Management', 'slug' => 'terminal-blocks-wire-management', 'sort_order' => 9],
                    [
                        'name' => 'Lamps & Bulbs',
                        'slug' => 'light-bulbs-lamps',
                        'sort_order' => 10,
                        'children' => [
                            ['name' => 'LED Bulbs', 'slug' => 'led-bulbs', 'sort_order' => 1],
                            ['name' => 'Fluorescent Tubes', 'slug' => 'fluorescent-tubes', 'sort_order' => 2],
                            ['name' => 'Halogen & Incandescent', 'slug' => 'halogen-incandescent', 'sort_order' => 3],
                        ],
                    ],
                    ['name' => 'Indoor Lighting Fixtures', 'slug' => 'indoor-lighting-fixtures', 'sort_order' => 11],
                    ['name' => 'Outdoor & Area Lighting', 'slug' => 'outdoor-area-lighting', 'sort_order' => 12],
                    ['name' => 'Emergency & Exit Lighting', 'slug' => 'emergency-exit-lighting', 'sort_order' => 13],
                    ['name' => 'Lighting Controls & Sensors', 'slug' => 'lighting-controls-sensors', 'sort_order' => 14],
                    ['name' => 'Ballasts & LED Drivers', 'slug' => 'ballasts-led-drivers', 'sort_order' => 15],
                    ['name' => 'Batteries & Chargers', 'slug' => 'batteries-chargers', 'sort_order' => 16],
                    ['name' => 'Generators & Inverters', 'slug' => 'generators-inverters', 'sort_order' => 17],
                    ['name' => 'Solar Panels & Accessories', 'slug' => 'solar-panels-accessories', 'sort_order' => 18],
                    ['name' => 'Electrical Test & Measurement', 'slug' => 'electrical-test-measurement', 'sort_order' => 19],
                ],
            ],

            // ══════════════════════════════════════════
            // 13. PLUMBING & HVAC
            // ══════════════════════════════════════════
            [
                'name' => 'Plumbing & HVAC',
                'code' => 'PL',
                'slug' => 'plumbing-hvac',
                'description' => 'Plumbing fixtures, piping, water treatment, HVAC, and refrigeration equipment.',
                'sort_order' => 13,
                'children' => [
                    ['name' => 'Pipes & Tubing', 'slug' => 'pipes-tubing', 'sort_order' => 1],
                    ['name' => 'Pipe Fittings & Connectors', 'slug' => 'pipe-fittings-connectors', 'sort_order' => 2],
                    ['name' => 'Valves', 'slug' => 'valves', 'sort_order' => 3],
                    ['name' => 'Faucets & Fixtures', 'slug' => 'faucets-fixtures', 'sort_order' => 4],
                    ['name' => 'Toilets & Urinals', 'slug' => 'toilets-urinals', 'sort_order' => 5],
                    ['name' => 'Sinks & Basins', 'slug' => 'sinks-basins', 'sort_order' => 6],
                    ['name' => 'Water Heaters', 'slug' => 'water-heaters', 'sort_order' => 7],
                    ['name' => 'Plumbing Pumps', 'slug' => 'pumps-plumbing', 'sort_order' => 8],
                    ['name' => 'Drain Cleaning & Tools', 'slug' => 'drain-cleaning-tools', 'sort_order' => 9],
                    ['name' => 'Water Filtration & Treatment', 'slug' => 'water-filtration-treatment', 'sort_order' => 10],
                    ['name' => 'HVAC Equipment', 'slug' => 'hvac-equipment', 'sort_order' => 11],
                    ['name' => 'Refrigeration Equipment', 'slug' => 'refrigeration-equipment', 'sort_order' => 12],
                    ['name' => 'Air Filters', 'slug' => 'air-filters', 'sort_order' => 13],
                    ['name' => 'Thermostats & Controls', 'slug' => 'thermostats-controls', 'sort_order' => 14],
                    ['name' => 'Ductwork, Vents & Fans', 'slug' => 'ductwork-vents', 'sort_order' => 15],
                    ['name' => 'Radiant & Baseboard Heating', 'slug' => 'radiant-baseboard-heating', 'sort_order' => 16],
                    ['name' => 'Gas & Propane Equipment', 'slug' => 'gas-propane-equipment', 'sort_order' => 17],
                ],
            ],

            // ══════════════════════════════════════════
            // 14. BUILDING & CONSTRUCTION
            // ══════════════════════════════════════════
            [
                'name' => 'Building & Construction',
                'code' => 'BC',
                'slug' => 'building-construction',
                'description' => 'Building materials, construction supplies, insulation, sealants, and hardware.',
                'sort_order' => 14,
                'children' => [
                    ['name' => 'Lumber & Plywood', 'slug' => 'lumber-plywood', 'sort_order' => 1],
                    ['name' => 'Drywall & Ceiling', 'slug' => 'drywall-ceiling', 'sort_order' => 2],
                    ['name' => 'Flooring & Carpet', 'slug' => 'flooring-carpet', 'sort_order' => 3],
                    ['name' => 'Paint & Coatings', 'slug' => 'paint-coatings', 'sort_order' => 4],
                    ['name' => 'Doors & Windows', 'slug' => 'doors-windows', 'sort_order' => 5],
                    ['name' => 'Builders Hardware & Fixings', 'slug' => 'hardware-builders', 'sort_order' => 6],
                    ['name' => 'Concrete & Masonry', 'slug' => 'concrete-masonry', 'sort_order' => 7],
                    ['name' => 'Roofing Materials', 'slug' => 'roofing-materials', 'sort_order' => 8],
                    ['name' => 'Fencing & Gates', 'slug' => 'fencing-gates', 'sort_order' => 9],
                    ['name' => 'Decking & Railing', 'slug' => 'decking-railing', 'sort_order' => 10],
                    ['name' => 'Insulation & Weatherproofing', 'slug' => 'insulation-weatherproofing', 'sort_order' => 11],
                    ['name' => 'Sealants & Caulk', 'slug' => 'sealants-caulk', 'sort_order' => 12],
                    ['name' => 'Siding & Trim', 'slug' => 'siding-trim', 'sort_order' => 13],
                    ['name' => 'Window Treatments & Blinds', 'slug' => 'window-treatments-blinds', 'sort_order' => 14],
                    ['name' => 'Jobsite Supplies & Equipment', 'slug' => 'jobsite-supplies-equipment', 'sort_order' => 15],
                ],
            ],

            // ══════════════════════════════════════════
            // 15. AUTOMOTIVE & FLEET
            // ══════════════════════════════════════════
            [
                'name' => 'Automotive & Fleet',
                'code' => 'AF',
                'slug' => 'automotive-fleet',
                'description' => 'Vehicle parts, fluids, accessories, shop equipment, and fleet safety.',
                'sort_order' => 15,
                'children' => [
                    ['name' => 'Brakes & Brake Parts', 'slug' => 'brakes-brake-parts', 'sort_order' => 1],
                    ['name' => 'Automotive Filters', 'slug' => 'filters-automotive', 'sort_order' => 2],
                    ['name' => 'Automotive Lighting', 'slug' => 'lighting-automotive', 'sort_order' => 3],
                    ['name' => 'Automotive Batteries', 'slug' => 'batteries-automotive', 'sort_order' => 4],
                    ['name' => 'Tires & Wheels', 'slug' => 'tires-wheels', 'sort_order' => 5],
                    ['name' => 'Automotive Fluids & Lubricants', 'slug' => 'automotive-fluids-lubricants', 'sort_order' => 6],
                    ['name' => 'Belts, Hoses & Cooling', 'slug' => 'belts-hoses-cooling', 'sort_order' => 7],
                    ['name' => 'Vehicle Accessories & Exterior', 'slug' => 'vehicle-accessories-exterior', 'sort_order' => 8],
                    ['name' => 'Car Care & Detailing', 'slug' => 'car-care-detailing', 'sort_order' => 9],
                    ['name' => 'Garage & Shop Equipment', 'slug' => 'garage-equipment', 'sort_order' => 10],
                    ['name' => 'Fleet Telematics & Safety', 'slug' => 'fleet-management', 'sort_order' => 11],
                    ['name' => 'Motorcycle & ATV', 'slug' => 'motorcycle-atv', 'sort_order' => 12],
                    ['name' => 'Towing & Trailers', 'slug' => 'towing-trailers', 'sort_order' => 13],
                    ['name' => 'EV Charging & Accessories', 'slug' => 'ev-charging-accessories', 'sort_order' => 14],
                ],
            ],

            // ══════════════════════════════════════════
            // 16. TEXTILES, LINENS & APPAREL
            // ══════════════════════════════════════════
            [
                'name' => 'Textiles, Linens & Apparel',
                'code' => 'TA',
                'slug' => 'textiles-apparel',
                'description' => 'Uniforms, workwear, promotional apparel, linens, and textiles.',
                'sort_order' => 16,
                'children' => [
                    ['name' => 'Uniforms & Workwear', 'slug' => 'work-uniforms', 'sort_order' => 1],
                    ['name' => 'Corporate Apparel', 'slug' => 'corporate-apparel', 'sort_order' => 2],
                    ['name' => 'Promotional Clothing', 'slug' => 'promotional-clothing', 'sort_order' => 3],
                    ['name' => 'Aprons & Protective Wear', 'slug' => 'aprons-protective-wear', 'sort_order' => 4],
                    ['name' => 'Towels & Linens', 'slug' => 'towels-linens', 'sort_order' => 5],
                    ['name' => 'Bedding & Mattresses', 'slug' => 'bedding-mattresses', 'sort_order' => 6],
                    ['name' => 'Bags & Backpacks', 'slug' => 'bags-backpacks', 'sort_order' => 7],
                    ['name' => 'Fabric & Raw Textiles', 'slug' => 'fabric-raw-textiles', 'sort_order' => 8],
                ],
            ],

            // ══════════════════════════════════════════
            // 17. SPORTS & RECREATION
            // ══════════════════════════════════════════
            [
                'name' => 'Sports & Recreation',
                'code' => 'SR',
                'slug' => 'sports-recreation',
                'description' => 'Sports equipment, fitness gear, outdoor recreation, and gym supplies.',
                'sort_order' => 17,
                'children' => [
                    ['name' => 'Fitness Equipment', 'slug' => 'fitness-equipment', 'sort_order' => 1],
                    ['name' => 'Gym Accessories', 'slug' => 'gym-accessories', 'sort_order' => 2],
                    ['name' => 'Team Sports', 'slug' => 'team-sports', 'sort_order' => 3],
                    ['name' => 'Team Uniforms', 'slug' => 'team-uniforms', 'sort_order' => 4],
                    ['name' => 'Water Sports', 'slug' => 'water-sports', 'sort_order' => 5],
                    ['name' => 'Outdoor & Camping', 'slug' => 'outdoor-camping', 'sort_order' => 6],
                    ['name' => 'Outdoor Adventure & Survival', 'slug' => 'outdoor-adventure-survival', 'sort_order' => 7],
                    ['name' => 'Cycling', 'slug' => 'cycling', 'sort_order' => 8],
                    ['name' => 'Sportswear & Footwear', 'slug' => 'sportswear-footwear', 'sort_order' => 9],
                    ['name' => 'Playground Equipment', 'slug' => 'playground-equipment', 'sort_order' => 10],
                ],
            ],

            // ══════════════════════════════════════════
            // 18. EDUCATIONAL SUPPLIES
            // ══════════════════════════════════════════
            [
                'name' => 'Educational Supplies',
                'code' => 'ED',
                'slug' => 'educational-supplies',
                'description' => 'Classroom supplies, furniture, STEM kits, and educational materials.',
                'sort_order' => 18,
                'children' => [
                    ['name' => 'Early Childhood & Preschool', 'slug' => 'early-childhood-preschool', 'sort_order' => 1],
                    ['name' => 'Classroom Furniture', 'slug' => 'classroom-furniture', 'sort_order' => 2],
                    ['name' => 'Teaching Aids & Resources', 'slug' => 'teaching-aids-resources', 'sort_order' => 3],
                    ['name' => 'Arts & Crafts Supplies', 'slug' => 'arts-crafts-supplies', 'sort_order' => 4],
                    ['name' => 'STEM & Science Kits', 'slug' => 'science-kits', 'sort_order' => 5],
                    ['name' => 'Books & Stationery', 'slug' => 'books-stationery', 'sort_order' => 6],
                    ['name' => 'Classroom AV & Technology', 'slug' => 'av-equipment-education', 'sort_order' => 7],
                    ['name' => 'Play & Learning Toys', 'slug' => 'play-learning-toys', 'sort_order' => 8],
                    ['name' => 'Special Needs & Sensory', 'slug' => 'special-needs-sensory', 'sort_order' => 9],
                ],
            ],

            // ══════════════════════════════════════════
            // 19. CHEMICALS & LUBRICANTS
            // ══════════════════════════════════════════
            [
                'name' => 'Chemicals & Lubricants',
                'code' => 'CL',
                'slug' => 'chemicals-lubricants',
                'description' => 'Industrial chemicals, lubricants, solvents, fuel additives, and specialty gases.',
                'sort_order' => 19,
                'children' => [
                    ['name' => 'Industrial Chemicals', 'slug' => 'industrial-chemicals', 'sort_order' => 1],
                    ['name' => 'Solvents & Thinners', 'slug' => 'solvents-thinners', 'sort_order' => 2],
                    ['name' => 'Lubricating Oils', 'slug' => 'lubricating-oils', 'sort_order' => 3],
                    ['name' => 'Greases', 'slug' => 'greases', 'sort_order' => 4],
                    ['name' => 'Coolants & Antifreeze', 'slug' => 'coolants-antifreeze', 'sort_order' => 5],
                    ['name' => 'Degreasers', 'slug' => 'degreasers', 'sort_order' => 6],
                    ['name' => 'Rust Treatments', 'slug' => 'rust-treatments', 'sort_order' => 7],
                    ['name' => 'Water Treatment Chemicals', 'slug' => 'water-treatment-chemicals', 'sort_order' => 8],
                    ['name' => 'Fuel & Fuel Additives', 'slug' => 'fuel-fuel-additives', 'sort_order' => 9],
                    ['name' => 'Specialty Gases', 'slug' => 'specialty-gases', 'sort_order' => 10],
                    ['name' => 'Surface Preparation', 'slug' => 'surface-preparation', 'sort_order' => 11],
                ],
            ],

            // ══════════════════════════════════════════
            // 20. PRINTING & PROMOTIONAL
            // ══════════════════════════════════════════
            [
                'name' => 'Printing & Promotional',
                'code' => 'PP',
                'slug' => 'printing-promotional',
                'description' => 'Printing services, signage, promotional products, and custom merchandise.',
                'sort_order' => 20,
                'children' => [
                    ['name' => 'Business Cards & Stationery', 'slug' => 'business-cards-stationery', 'sort_order' => 1],
                    ['name' => 'Signage & Displays', 'slug' => 'banners-signs', 'sort_order' => 2],
                    [
                        'name' => 'Promotional Products',
                        'slug' => 'promotional-products',
                        'sort_order' => 3,
                        'children' => [
                            ['name' => 'Pens & Desk Items', 'slug' => 'pens-desk-items', 'sort_order' => 1],
                            ['name' => 'Drinkware', 'slug' => 'drinkware', 'sort_order' => 2],
                            ['name' => 'Bags & Totes', 'slug' => 'bags-totes', 'sort_order' => 3],
                            ['name' => 'Tech Accessories', 'slug' => 'tech-accessories', 'sort_order' => 4],
                        ],
                    ],
                    ['name' => 'Apparel Printing & Embroidery', 'slug' => 'custom-apparel', 'sort_order' => 4],
                    ['name' => 'Brochures & Flyers', 'slug' => 'brochures-flyers', 'sort_order' => 5],
                    ['name' => 'Trophies & Awards', 'slug' => 'trophies-awards', 'sort_order' => 6],
                    ['name' => 'Exhibition & Trade Show', 'slug' => 'exhibition-trade-show', 'sort_order' => 7],
                    ['name' => 'Labels & Decals', 'slug' => 'labels-decals', 'sort_order' => 8],
                    ['name' => 'Direct Mail & Postcards', 'slug' => 'direct-mail-postcards', 'sort_order' => 9],
                ],
            ],

            // ══════════════════════════════════════════
            // 21. GARDENING & LANDSCAPING
            // ══════════════════════════════════════════
            [
                'name' => 'Gardening & Landscaping',
                'code' => 'GL',
                'slug' => 'gardening-landscaping',
                'description' => 'Garden tools, outdoor power equipment, plants, and landscaping supplies.',
                'sort_order' => 21,
                'children' => [
                    ['name' => 'Garden Tools', 'slug' => 'garden-tools', 'sort_order' => 1],
                    ['name' => 'Lawn Mowers & Trimmers', 'slug' => 'lawn-mowers-trimmers', 'sort_order' => 2],
                    ['name' => 'Outdoor Power Equipment', 'slug' => 'outdoor-power-equipment', 'sort_order' => 3],
                    ['name' => 'Irrigation & Sprinklers', 'slug' => 'irrigation-sprinklers', 'sort_order' => 4],
                    ['name' => 'Hoses & Watering', 'slug' => 'hoses-watering', 'sort_order' => 5],
                    ['name' => 'Plants & Seeds', 'slug' => 'plants-seeds', 'sort_order' => 6],
                    ['name' => 'Fertilizers & Soil', 'slug' => 'fertilizers-soil', 'sort_order' => 7],
                    ['name' => 'Compost & Mulch', 'slug' => 'compost-mulch', 'sort_order' => 8],
                    ['name' => 'Landscape Edging & Fabric', 'slug' => 'landscape-edging-fabric', 'sort_order' => 9],
                    ['name' => 'Greenhouses & Cold Frames', 'slug' => 'greenhouses-cold-frames', 'sort_order' => 10],
                    ['name' => 'Garden Pest Control', 'slug' => 'pest-control-garden', 'sort_order' => 11],
                    ['name' => 'Outdoor Decor', 'slug' => 'outdoor-decor', 'sort_order' => 12],
                    ['name' => 'Pots & Planters', 'slug' => 'pots-planters', 'sort_order' => 13],
                    ['name' => 'Snow & Ice Removal', 'slug' => 'snow-ice-removal', 'sort_order' => 14],
                ],
            ],

            // ══════════════════════════════════════════
            // 22. ROBOTICS & AUTOMATION
            // ══════════════════════════════════════════
            [
                'name' => 'Robotics & Automation',
                'code' => 'RB',
                'slug' => 'robotics-automation',
                'description' => 'Robotics components, cobots, drones, machine vision, and industrial automation.',
                'sort_order' => 22,
                'children' => [
                    ['name' => 'Educational & STEM Kits', 'slug' => 'educational-stem-kits', 'sort_order' => 1],
                    ['name' => 'Motors & Actuators', 'slug' => 'motors-actuators', 'sort_order' => 2],
                    ['name' => 'Robot Sensors & Modules', 'slug' => 'sensors-modules', 'sort_order' => 3],
                    ['name' => 'Robot Controllers & Boards', 'slug' => 'controllers-boards', 'sort_order' => 4],
                    ['name' => 'Robot Power & Batteries', 'slug' => 'power-batteries', 'sort_order' => 5],
                    ['name' => 'Drone & UAV Parts', 'slug' => 'drone-uav-parts', 'sort_order' => 6],
                    ['name' => 'Agricultural Drone Components', 'slug' => 'agricultural-drone-components', 'sort_order' => 7],
                    ['name' => 'Robot Chassis & Wheels', 'slug' => 'robot-chassis-wheels', 'sort_order' => 8],
                    ['name' => 'Robot Arms & Cobots', 'slug' => 'robot-arms-cobots', 'sort_order' => 9],
                    ['name' => 'AGV & AMR Systems', 'slug' => 'agv-amr-systems', 'sort_order' => 10],
                    ['name' => 'End Effectors & Grippers', 'slug' => 'end-effectors-grippers', 'sort_order' => 11],
                    ['name' => 'Machine Vision', 'slug' => 'machine-vision', 'sort_order' => 12],
                    [
                        'name' => 'Industrial Automation',
                        'slug' => 'industrial-automation',
                        'sort_order' => 13,
                        'children' => [
                            ['name' => 'PLCs, HMIs & Drives', 'slug' => 'plcs-hmis-drives', 'sort_order' => 1],
                            ['name' => 'Machine Safety', 'slug' => 'machine-safety', 'sort_order' => 2],
                        ],
                    ],
                    ['name' => 'Robot Cabling & Hardware', 'slug' => 'cables-connectors-hardware', 'sort_order' => 14],
                ],
            ],
        ];
    }
}
