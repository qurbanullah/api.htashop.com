<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Category;
use Illuminate\Support\Str;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = $this->categories();
        $reservedCodes = $this->explicitCodes($categories);

        Category::withoutEvents(function () use ($categories, $reservedCodes) {
            foreach ($categories as $data) {
                $this->seedCategory($data, null, $reservedCodes);
            }
        });

        $this->command->info('Product categories seeded.');
    }

    protected function seedCategory(array $data, ?int $parentId = null, array $reservedCodes = []): void
    {
        $children = $data['children'] ?? [];
        unset($data['children']);

        $data['parent_id'] = $parentId;
        $data['is_active'] = $data['is_active'] ?? true;

        if (! empty($data['code'])) {
            // Explicit codes always win. If an auto-generated code from a previous
            // run was assigned to the wrong category, take it back and re-assign.
            $data['code'] = $this->claimExplicitCode($data['code'], $data['slug'], $reservedCodes);
        } elseif (empty($data['code'])) {
            $existing = Category::withTrashed()->where('slug', $data['slug'])->first();
            $data['code'] = $existing?->code ?? $this->resolveCategoryCode($data['name'], $reservedCodes);
        }

        $category = Category::updateOrCreate(
            ['slug' => $data['slug']],
            $data
        );

        foreach ($children as $child) {
            $this->seedCategory($child, $category->id, $reservedCodes);
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
            $candidate = chr(65 + intdiv($i, 26)) . chr(65 + ($i % 26));
            if (! $used->has($candidate)) {
                return $candidate;
            }
        }

        return $base;
    }

    private function categories(): array
    {
        return [

            // ══════════════════════════════════════════
            //  1. IT & ELECTRONICS
            // ══════════════════════════════════════════
            [
                'name' => 'IT & Electronics',
                'code' => 'IT',
                'slug' => 'it-electronics',
                'description' => 'Computers, peripherals, networking, audio-visual, and electronic devices.',
                'sort_order' => 1,
                'children' => [
                    ['name' => 'Laptops & Notebooks', 'slug' => 'laptops-notebooks', 'sort_order' => 1],
                    ['name' => 'Desktop Computers', 'slug' => 'desktop-computers', 'sort_order' => 2],
                    ['name' => 'Monitors & Displays', 'slug' => 'monitors-displays', 'sort_order' => 3],
                    ['name' => 'Printers, Scanners & Copiers', 'slug' => 'printers-scanners-copiers', 'sort_order' => 4],
                    ['name' => 'Computer Components', 'slug' => 'computer-components', 'sort_order' => 5],
                    ['name' => 'Computer Accessories', 'slug' => 'computer-accessories', 'sort_order' => 6],
                    ['name' => 'Networking Equipment', 'slug' => 'networking-equipment', 'sort_order' => 7],
                    ['name' => 'Servers & Storage', 'slug' => 'servers-storage', 'sort_order' => 8],
                    ['name' => 'Software & Licenses', 'slug' => 'software-licenses', 'sort_order' => 9],
                    ['name' => 'Cables & Adapters', 'slug' => 'cables-adapters', 'sort_order' => 10],
                    ['name' => 'Power & UPS', 'slug' => 'power-ups', 'sort_order' => 11],
                    ['name' => 'Audio & Headsets', 'slug' => 'audio-headsets', 'sort_order' => 12],
                    ['name' => 'Webcams & Video Conferencing', 'slug' => 'webcams-video-conferencing', 'sort_order' => 13],
                    ['name' => 'Tablets & e-Readers', 'slug' => 'tablets-ereaders', 'sort_order' => 14],
                    ['name' => 'Mobile Phones & Accessories', 'slug' => 'mobile-phones-accessories', 'sort_order' => 15],
                    ['name' => 'Cameras & Photography', 'slug' => 'cameras-photography', 'sort_order' => 16],
                    ['name' => 'Smart Devices & IoT', 'slug' => 'smart-devices-iot', 'sort_order' => 17],
                ],
            ],

            // ══════════════════════════════════════════
            //  2. OFFICE SUPPLIES
            // ══════════════════════════════════════════
            [
                'name' => 'Office Supplies',
                'code' => 'OS',
                'slug' => 'office-supplies',
                'description' => 'Paper, writing instruments, desk organization, and daily office consumables.',
                'sort_order' => 2,
                'children' => [
                    ['name' => 'Paper & Copy Paper', 'slug' => 'paper-copy-paper', 'sort_order' => 1],
                    ['name' => 'Notebooks & Pads', 'slug' => 'notebooks-pads', 'sort_order' => 2],
                    ['name' => 'Writing Instruments', 'slug' => 'writing-instruments', 'sort_order' => 3],
                    ['name' => 'Markers & Highlighters', 'slug' => 'markers-highlighters', 'sort_order' => 4],
                    ['name' => 'Desk Organizers & Accessories', 'slug' => 'desk-organizers-accessories', 'sort_order' => 5],
                    ['name' => 'Filing & Storage', 'slug' => 'filing-storage', 'sort_order' => 6],
                    ['name' => 'Folders & Binders', 'slug' => 'folders-binders', 'sort_order' => 7],
                    ['name' => 'Staplers & Punches', 'slug' => 'staplers-punches', 'sort_order' => 8],
                    ['name' => 'Tape, Glue & Adhesives', 'slug' => 'tape-glue-adhesives', 'sort_order' => 9],
                    ['name' => 'Envelopes & Mailers', 'slug' => 'envelopes-mailers', 'sort_order' => 10],
                    ['name' => 'Calendars & Planners', 'slug' => 'calendars-planners', 'sort_order' => 11],
                    ['name' => 'Presentation Supplies', 'slug' => 'presentation-supplies', 'sort_order' => 12],
                    ['name' => 'Whiteboards & Bulletin Boards', 'slug' => 'whiteboards-bulletin-boards', 'sort_order' => 13],
                    ['name' => 'Stamps & Ink Pads', 'slug' => 'stamps-ink-pads', 'sort_order' => 14],
                    ['name' => 'Calculators', 'slug' => 'calculators', 'sort_order' => 15],
                ],
            ],

            // ══════════════════════════════════════════
            //  3. FURNITURE
            // ══════════════════════════════════════════
            [
                'name' => 'Furniture',
                'code' => 'FU',
                'slug' => 'furniture',
                'description' => 'Office, workplace, and institutional furniture.',
                'sort_order' => 3,
                'children' => [
                    ['name' => 'Office Chairs & Seating', 'slug' => 'office-chairs-seating', 'sort_order' => 1],
                    ['name' => 'Ergonomic Chairs', 'slug' => 'ergonomic-chairs', 'sort_order' => 2],
                    ['name' => 'Desks & Workstations', 'slug' => 'desks-workstations', 'sort_order' => 3],
                    ['name' => 'Standing Desks', 'slug' => 'standing-desks', 'sort_order' => 4],
                    ['name' => 'Storage Cabinets & Shelving', 'slug' => 'storage-cabinets-shelving', 'sort_order' => 5],
                    ['name' => 'Bookcases', 'slug' => 'bookcases', 'sort_order' => 6],
                    ['name' => 'Meeting & Conference Tables', 'slug' => 'meeting-conference-tables', 'sort_order' => 7],
                    ['name' => 'Reception Furniture', 'slug' => 'reception-furniture', 'sort_order' => 8],
                    ['name' => 'Breakroom Furniture', 'slug' => 'breakroom-furniture', 'sort_order' => 9],
                    ['name' => 'Outdoor Furniture', 'slug' => 'outdoor-furniture', 'sort_order' => 10],
                    ['name' => 'Carts & Trolleys', 'slug' => 'carts-trolleys', 'sort_order' => 11],
                    ['name' => 'Partitions & Panels', 'slug' => 'partitions-panels', 'sort_order' => 12],
                ],
            ],

            // ══════════════════════════════════════════
            //  4. MRO & INDUSTRIAL
            // ══════════════════════════════════════════
            [
                'name' => 'MRO & Industrial',
                'code' => 'MR',
                'slug' => 'mro-industrial',
                'description' => 'Maintenance, repair, operations, and industrial supplies.',
                'sort_order' => 4,
                'children' => [
                    ['name' => 'Hand Tools', 'slug' => 'hand-tools', 'sort_order' => 1],
                    ['name' => 'Power Tools', 'slug' => 'power-tools', 'sort_order' => 2],
                    ['name' => 'Tool Storage', 'slug' => 'tool-storage', 'sort_order' => 3],
                    ['name' => 'Measuring & Layout Tools', 'slug' => 'measuring-layout-tools', 'sort_order' => 4],
                    ['name' => 'Abrasives & Grinding', 'slug' => 'abrasives-grinding', 'sort_order' => 5],
                    ['name' => 'Cutting Tools', 'slug' => 'cutting-tools', 'sort_order' => 6],
                    ['name' => 'Fasteners & Hardware', 'slug' => 'fasteners-hardware', 'sort_order' => 7],
                    ['name' => 'Adhesives, Sealants & Tapes', 'slug' => 'adhesives-sealants-tapes', 'sort_order' => 8],
                    ['name' => 'Lubricants & Greases', 'slug' => 'lubricants-greases', 'sort_order' => 9],
                    ['name' => 'Material Handling', 'slug' => 'material-handling', 'sort_order' => 10],
                    ['name' => 'Pumps & Motors', 'slug' => 'pumps-motors', 'sort_order' => 11],
                    ['name' => 'Compressors & Pneumatics', 'slug' => 'compressors-pneumatics', 'sort_order' => 12],
                    ['name' => 'Hydraulics', 'slug' => 'hydraulics', 'sort_order' => 13],
                    ['name' => 'Bearings & Power Transmission', 'slug' => 'bearings-power-transmission', 'sort_order' => 14],
                    ['name' => 'Welding & Soldering', 'slug' => 'welding-soldering', 'sort_order' => 15],
                    ['name' => 'Ladders & Scaffolding', 'slug' => 'ladders-scaffolding', 'sort_order' => 16],
                ],
            ],

            // ══════════════════════════════════════════
            //  5. SAFETY & PPE
            // ══════════════════════════════════════════
            [
                'name' => 'Safety & PPE',
                'code' => 'SP',
                'slug' => 'safety-ppe',
                'description' => 'Personal protective equipment and workplace safety supplies.',
                'sort_order' => 5,
                'children' => [
                    ['name' => 'Eye & Face Protection', 'slug' => 'eye-face-protection', 'sort_order' => 1],
                    ['name' => 'Hand Protection (Gloves)', 'slug' => 'hand-protection', 'sort_order' => 2],
                    ['name' => 'Head Protection', 'slug' => 'head-protection', 'sort_order' => 3],
                    ['name' => 'Hearing Protection', 'slug' => 'hearing-protection', 'sort_order' => 4],
                    ['name' => 'Respiratory Protection', 'slug' => 'respiratory-protection', 'sort_order' => 5],
                    ['name' => 'Foot Protection', 'slug' => 'foot-protection', 'sort_order' => 6],
                    ['name' => 'Protective Clothing', 'slug' => 'protective-clothing', 'sort_order' => 7],
                    ['name' => 'Fall Protection', 'slug' => 'fall-protection', 'sort_order' => 8],
                    ['name' => 'Fire Safety', 'slug' => 'fire-safety', 'sort_order' => 9],
                    ['name' => 'First Aid & Emergency', 'slug' => 'first-aid-emergency', 'sort_order' => 10],
                    ['name' => 'Safety Signs & Labels', 'slug' => 'safety-signs-labels', 'sort_order' => 11],
                    ['name' => 'Traffic & Parking Safety', 'slug' => 'traffic-parking-safety', 'sort_order' => 12],
                    ['name' => 'Spill Control', 'slug' => 'spill-control', 'sort_order' => 13],
                    ['name' => 'Lockout Tagout (LOTO)', 'slug' => 'lockout-tagout-loto', 'sort_order' => 14],
                ],
            ],

            // ══════════════════════════════════════════
            //  6. CLEANING & JANITORIAL
            // ══════════════════════════════════════════
            [
                'name' => 'Cleaning & Janitorial',
                'code' => 'CJ',
                'slug' => 'cleaning-janitorial',
                'description' => 'Cleaning supplies, chemicals, equipment, and sanitary products.',
                'sort_order' => 6,
                'children' => [
                    ['name' => 'Cleaning Chemicals', 'slug' => 'cleaning-chemicals', 'sort_order' => 1],
                    ['name' => 'Disinfectants & Sanitizers', 'slug' => 'disinfectants-sanitizers', 'sort_order' => 2],
                    ['name' => 'Paper Products & Dispensers', 'slug' => 'paper-products-dispensers', 'sort_order' => 3],
                    ['name' => 'Cleaning Tools & Mops', 'slug' => 'cleaning-tools-mops', 'sort_order' => 4],
                    ['name' => 'Brooms, Brushes & Dustpans', 'slug' => 'brooms-brushes-dustpans', 'sort_order' => 5],
                    ['name' => 'Trash Bags & Liners', 'slug' => 'trash-bags-liners', 'sort_order' => 6],
                    ['name' => 'Waste & Recycling Bins', 'slug' => 'waste-recycling-bins', 'sort_order' => 7],
                    ['name' => 'Vacuum Cleaners & Floor Care', 'slug' => 'vacuum-cleaners-floor-care', 'sort_order' => 8],
                    ['name' => 'Restroom Supplies', 'slug' => 'restroom-supplies', 'sort_order' => 9],
                    ['name' => 'Air Fresheners & Odor Control', 'slug' => 'air-fresheners-odor-control', 'sort_order' => 10],
                    ['name' => 'Laundry Supplies', 'slug' => 'laundry-supplies', 'sort_order' => 11],
                ],
            ],

            // ══════════════════════════════════════════
            //  7. PACKAGING & SHIPPING
            // ══════════════════════════════════════════
            [
                'name' => 'Packaging & Shipping',
                'code' => 'PS',
                'slug' => 'packaging-shipping',
                'description' => 'Packaging materials, shipping supplies, and mailroom equipment.',
                'sort_order' => 7,
                'children' => [
                    ['name' => 'Boxes & Cartons', 'slug' => 'boxes-cartons', 'sort_order' => 1],
                    ['name' => 'Padded Mailers & Envelopes', 'slug' => 'padded-mailers-envelopes', 'sort_order' => 2],
                    ['name' => 'Packing Tape & Dispensers', 'slug' => 'packing-tape-dispensers', 'sort_order' => 3],
                    ['name' => 'Bubble Wrap & Cushioning', 'slug' => 'bubble-wrap-cushioning', 'sort_order' => 4],
                    ['name' => 'Packing Peanuts & Foam', 'slug' => 'packing-peanuts-foam', 'sort_order' => 5],
                    ['name' => 'Stretch & Shrink Wrap', 'slug' => 'stretch-shrink-wrap', 'sort_order' => 6],
                    ['name' => 'Strapping & Banding', 'slug' => 'strapping-banding', 'sort_order' => 7],
                    ['name' => 'Shipping Labels & Printers', 'slug' => 'shipping-labels-printers', 'sort_order' => 8],
                    ['name' => 'Postal Scales & Meters', 'slug' => 'postal-scales-meters', 'sort_order' => 9],
                    ['name' => 'Pallets & Dunnage', 'slug' => 'pallets-dunnage', 'sort_order' => 10],
                ],
            ],

            // ══════════════════════════════════════════
            //  8. LAB & SCIENTIFIC
            // ══════════════════════════════════════════
            [
                'name' => 'Lab & Scientific',
                'code' => 'LB',
                'slug' => 'lab-scientific',
                'description' => 'Laboratory equipment, instruments, consumables, and chemicals.',
                'sort_order' => 8,
                'children' => [
                    ['name' => 'Lab Equipment & Instruments', 'slug' => 'lab-equipment-instruments', 'sort_order' => 1],
                    ['name' => 'Glassware & Plasticware', 'slug' => 'glassware-plasticware', 'sort_order' => 2],
                    ['name' => 'Chemicals & Reagents', 'slug' => 'chemicals-reagents', 'sort_order' => 3],
                    ['name' => 'Test, Measurement & Inspection', 'slug' => 'test-measurement-inspection', 'sort_order' => 4],
                    ['name' => 'Microscopes & Optics', 'slug' => 'microscopes-optics', 'sort_order' => 5],
                    ['name' => 'Centrifuges & Mixers', 'slug' => 'centrifuges-mixers', 'sort_order' => 6],
                    ['name' => 'Pipettes & Liquid Handling', 'slug' => 'pipettes-liquid-handling', 'sort_order' => 7],
                    ['name' => 'Lab Safety & PPE', 'slug' => 'lab-safety-ppe', 'sort_order' => 8],
                    ['name' => 'Chromatography Supplies', 'slug' => 'chromatography-supplies', 'sort_order' => 9],
                ],
            ],

            // ══════════════════════════════════════════
            //  9. MEDICAL & HEALTHCARE
            // ══════════════════════════════════════════
            [
                'name' => 'Medical & Healthcare',
                'code' => 'MD',
                'slug' => 'medical-healthcare',
                'description' => 'Medical supplies, equipment, and healthcare products.',
                'sort_order' => 9,
                'children' => [
                    ['name' => 'Medical Equipment', 'slug' => 'medical-equipment', 'sort_order' => 1],
                    ['name' => 'Diagnostic Instruments', 'slug' => 'diagnostic-instruments', 'sort_order' => 2],
                    ['name' => 'Surgical Supplies', 'slug' => 'surgical-supplies', 'sort_order' => 3],
                    ['name' => 'Syringes & Needles', 'slug' => 'syringes-needles', 'sort_order' => 4],
                    ['name' => 'Bandages & Wound Care', 'slug' => 'bandages-wound-care', 'sort_order' => 5],
                    ['name' => 'Gloves (Medical)', 'slug' => 'gloves-medical', 'sort_order' => 6],
                    ['name' => 'Masks & Face Shields', 'slug' => 'masks-face-shields', 'sort_order' => 7],
                    ['name' => 'Gowns & Aprons', 'slug' => 'gowns-aprons', 'sort_order' => 8],
                    ['name' => 'Patient Care & Mobility', 'slug' => 'patient-care-mobility', 'sort_order' => 9],
                    ['name' => 'Pharmaceuticals & OTC', 'slug' => 'pharmaceuticals-otc', 'sort_order' => 10],
                    ['name' => 'Dental Supplies', 'slug' => 'dental-supplies', 'sort_order' => 11],
                    ['name' => 'Veterinary Supplies', 'slug' => 'veterinary-supplies', 'sort_order' => 12],
                ],
            ],

            // ══════════════════════════════════════════
            // 10. FOOD & BEVERAGE
            // ══════════════════════════════════════════
            [
                'name' => 'Food & Beverage',
                'code' => 'FB',
                'slug' => 'food-beverage',
                'description' => 'Food products, beverages, breakroom supplies, and catering essentials.',
                'sort_order' => 10,
                'children' => [
                    ['name' => 'Coffee & Tea', 'slug' => 'coffee-tea', 'sort_order' => 1],
                    ['name' => 'Water & Beverages', 'slug' => 'water-beverages', 'sort_order' => 2],
                    ['name' => 'Snacks & Confectionery', 'slug' => 'snacks-confectionery', 'sort_order' => 3],
                    ['name' => 'Breakroom Supplies', 'slug' => 'breakroom-supplies', 'sort_order' => 4],
                    ['name' => 'Disposable Cups & Utensils', 'slug' => 'disposable-cups-utensils', 'sort_order' => 5],
                    ['name' => 'Napkins & Paper Towels', 'slug' => 'napkins-paper-towels', 'sort_order' => 6],
                    ['name' => 'Food Storage Containers', 'slug' => 'food-storage-containers', 'sort_order' => 7],
                    ['name' => 'Catering Equipment', 'slug' => 'catering-equipment', 'sort_order' => 8],
                    ['name' => 'Vending Machine Supplies', 'slug' => 'vending-machine-supplies', 'sort_order' => 9],
                ],
            ],

            // ══════════════════════════════════════════
            // 11. ELECTRICAL & LIGHTING
            // ══════════════════════════════════════════
            [
                'name' => 'Electrical & Lighting',
                'code' => 'EL',
                'slug' => 'electrical-lighting',
                'description' => 'Electrical supplies, wiring, lighting, and power distribution.',
                'sort_order' => 11,
                'children' => [
                    ['name' => 'Wires & Cables', 'slug' => 'wires-cables', 'sort_order' => 1],
                    ['name' => 'Switches & Outlets', 'slug' => 'switches-outlets', 'sort_order' => 2],
                    ['name' => 'Circuit Breakers & Panels', 'slug' => 'circuit-breakers-panels', 'sort_order' => 3],
                    ['name' => 'Conduit & Fittings', 'slug' => 'conduit-fittings', 'sort_order' => 4],
                    ['name' => 'LED Lighting', 'slug' => 'led-lighting', 'sort_order' => 5],
                    ['name' => 'Fluorescent Lighting', 'slug' => 'fluorescent-lighting', 'sort_order' => 6],
                    ['name' => 'Emergency & Exit Lighting', 'slug' => 'emergency-exit-lighting', 'sort_order' => 7],
                    ['name' => 'Outdoor & Area Lighting', 'slug' => 'outdoor-area-lighting', 'sort_order' => 8],
                    ['name' => 'Light Bulbs & Lamps', 'slug' => 'light-bulbs-lamps', 'sort_order' => 9],
                    ['name' => 'Batteries & Chargers', 'slug' => 'batteries-chargers', 'sort_order' => 10],
                    ['name' => 'Generators & Inverters', 'slug' => 'generators-inverters', 'sort_order' => 11],
                    ['name' => 'Solar Panels & Accessories', 'slug' => 'solar-panels-accessories', 'sort_order' => 12],
                ],
            ],

            // ══════════════════════════════════════════
            // 12. PLUMBING & HVAC
            // ══════════════════════════════════════════
            [
                'name' => 'Plumbing & HVAC',
                'code' => 'PL',
                'slug' => 'plumbing-hvac',
                'description' => 'Plumbing fixtures, pipes, fittings, heating, ventilation, and air conditioning.',
                'sort_order' => 12,
                'children' => [
                    ['name' => 'Pipes & Tubing', 'slug' => 'pipes-tubing', 'sort_order' => 1],
                    ['name' => 'Pipe Fittings & Connectors', 'slug' => 'pipe-fittings-connectors', 'sort_order' => 2],
                    ['name' => 'Valves', 'slug' => 'valves', 'sort_order' => 3],
                    ['name' => 'Faucets & Fixtures', 'slug' => 'faucets-fixtures', 'sort_order' => 4],
                    ['name' => 'Toilets & Urinals', 'slug' => 'toilets-urinals', 'sort_order' => 5],
                    ['name' => 'Sinks & Basins', 'slug' => 'sinks-basins', 'sort_order' => 6],
                    ['name' => 'Water Heaters', 'slug' => 'water-heaters', 'sort_order' => 7],
                    ['name' => 'Pumps (Plumbing)', 'slug' => 'pumps-plumbing', 'sort_order' => 8],
                    ['name' => 'HVAC Equipment', 'slug' => 'hvac-equipment', 'sort_order' => 9],
                    ['name' => 'Air Filters', 'slug' => 'air-filters', 'sort_order' => 10],
                    ['name' => 'Thermostats & Controls', 'slug' => 'thermostats-controls', 'sort_order' => 11],
                    ['name' => 'Ductwork & Vents', 'slug' => 'ductwork-vents', 'sort_order' => 12],
                    ['name' => 'Insulation', 'slug' => 'insulation', 'sort_order' => 13],
                ],
            ],

            // ══════════════════════════════════════════
            // 13. BUILDING & CONSTRUCTION
            // ══════════════════════════════════════════
            [
                'name' => 'Building & Construction',
                'code' => 'BC',
                'slug' => 'building-construction',
                'description' => 'Building materials, construction supplies, and hardware.',
                'sort_order' => 13,
                'children' => [
                    ['name' => 'Lumber & Plywood', 'slug' => 'lumber-plywood', 'sort_order' => 1],
                    ['name' => 'Drywall & Ceiling', 'slug' => 'drywall-ceiling', 'sort_order' => 2],
                    ['name' => 'Flooring & Carpet', 'slug' => 'flooring-carpet', 'sort_order' => 3],
                    ['name' => 'Paint & Coatings', 'slug' => 'paint-coatings', 'sort_order' => 4],
                    ['name' => 'Doors & Windows', 'slug' => 'doors-windows', 'sort_order' => 5],
                    ['name' => 'Hardware (Builders)', 'slug' => 'hardware-builders', 'sort_order' => 6],
                    ['name' => 'Concrete & Masonry', 'slug' => 'concrete-masonry', 'sort_order' => 7],
                    ['name' => 'Roofing Materials', 'slug' => 'roofing-materials', 'sort_order' => 8],
                    ['name' => 'Fencing & Gates', 'slug' => 'fencing-gates', 'sort_order' => 9],
                    ['name' => 'Decking & Railing', 'slug' => 'decking-railing', 'sort_order' => 10],
                ],
            ],

            // ══════════════════════════════════════════
            // 14. AUTOMOTIVE & FLEET
            // ══════════════════════════════════════════
            [
                'name' => 'Automotive & Fleet',
                'code' => 'AF',
                'slug' => 'automotive-fleet',
                'description' => 'Vehicle parts, maintenance supplies, and fleet management products.',
                'sort_order' => 14,
                'children' => [
                    ['name' => 'Vehicle Parts & Accessories', 'slug' => 'vehicle-parts-accessories', 'sort_order' => 1],
                    ['name' => 'Tires & Wheels', 'slug' => 'tires-wheels', 'sort_order' => 2],
                    ['name' => 'Automotive Fluids & Lubricants', 'slug' => 'automotive-fluids-lubricants', 'sort_order' => 3],
                    ['name' => 'Batteries (Automotive)', 'slug' => 'batteries-automotive', 'sort_order' => 4],
                    ['name' => 'Filters (Automotive)', 'slug' => 'filters-automotive', 'sort_order' => 5],
                    ['name' => 'Brakes & Brake Parts', 'slug' => 'brakes-brake-parts', 'sort_order' => 6],
                    ['name' => 'Lighting (Automotive)', 'slug' => 'lighting-automotive', 'sort_order' => 7],
                    ['name' => 'Car Care & Detailing', 'slug' => 'car-care-detailing', 'sort_order' => 8],
                    ['name' => 'Garage Equipment', 'slug' => 'garage-equipment', 'sort_order' => 9],
                    ['name' => 'Fleet Management', 'slug' => 'fleet-management', 'sort_order' => 10],
                ],
            ],

            // ══════════════════════════════════════════
            // 15. TEXTILES & APPAREL
            // ══════════════════════════════════════════
            [
                'name' => 'Textiles & Apparel',
                'code' => 'TA',
                'slug' => 'textiles-apparel',
                'description' => 'Uniforms, workwear, promotional apparel, linens, and textiles.',
                'sort_order' => 15,
                'children' => [
                    ['name' => 'Work Uniforms', 'slug' => 'work-uniforms', 'sort_order' => 1],
                    ['name' => 'Corporate Apparel', 'slug' => 'corporate-apparel', 'sort_order' => 2],
                    ['name' => 'Promotional Clothing', 'slug' => 'promotional-clothing', 'sort_order' => 3],
                    ['name' => 'Safety Workwear', 'slug' => 'safety-workwear', 'sort_order' => 4],
                    ['name' => 'Towels & Linens', 'slug' => 'towels-linens', 'sort_order' => 5],
                    ['name' => 'Bedding & Mattresses', 'slug' => 'bedding-mattresses', 'sort_order' => 6],
                    ['name' => 'Curtains & Blinds', 'slug' => 'curtains-blinds', 'sort_order' => 7],
                    ['name' => 'Bags & Backpacks', 'slug' => 'bags-backpacks', 'sort_order' => 8],
                    ['name' => 'Fabric & Raw Textiles', 'slug' => 'fabric-raw-textiles', 'sort_order' => 9],
                ],
            ],

            // ══════════════════════════════════════════
            // 16. SPORTS & RECREATION
            // ══════════════════════════════════════════
            [
                'name' => 'Sports & Recreation',
                'code' => 'SR',
                'slug' => 'sports-recreation',
                'description' => 'Sports equipment, fitness gear, outdoor recreation, and gym supplies.',
                'sort_order' => 16,
                'children' => [
                    ['name' => 'Fitness Equipment', 'slug' => 'fitness-equipment', 'sort_order' => 1],
                    ['name' => 'Gym Accessories', 'slug' => 'gym-accessories', 'sort_order' => 2],
                    ['name' => 'Team Sports', 'slug' => 'team-sports', 'sort_order' => 3],
                    ['name' => 'Water Sports', 'slug' => 'water-sports', 'sort_order' => 4],
                    ['name' => 'Outdoor & Camping', 'slug' => 'outdoor-camping', 'sort_order' => 5],
                    ['name' => 'Cycling', 'slug' => 'cycling', 'sort_order' => 6],
                    ['name' => 'Sportswear & Footwear', 'slug' => 'sportswear-footwear', 'sort_order' => 7],
                    ['name' => 'Playground Equipment', 'slug' => 'playground-equipment', 'sort_order' => 8],
                ],
            ],

            // ══════════════════════════════════════════
            // 17. EDUCATIONAL SUPPLIES
            // ══════════════════════════════════════════
            [
                'name' => 'Educational Supplies',
                'code' => 'ED',
                'slug' => 'educational-supplies',
                'description' => 'Classroom supplies, teaching aids, and educational materials.',
                'sort_order' => 17,
                'children' => [
                    ['name' => 'Classroom Furniture', 'slug' => 'classroom-furniture', 'sort_order' => 1],
                    ['name' => 'Teaching Aids & Resources', 'slug' => 'teaching-aids-resources', 'sort_order' => 2],
                    ['name' => 'Arts & Crafts Supplies', 'slug' => 'arts-crafts-supplies', 'sort_order' => 3],
                    ['name' => 'Science Kits', 'slug' => 'science-kits', 'sort_order' => 4],
                    ['name' => 'Books & Stationery', 'slug' => 'books-stationery', 'sort_order' => 5],
                    ['name' => 'AV Equipment (Education)', 'slug' => 'av-equipment-education', 'sort_order' => 6],
                    ['name' => 'Play & Learning Toys', 'slug' => 'play-learning-toys', 'sort_order' => 7],
                ],
            ],

            // ══════════════════════════════════════════
            // 18. CHEMICALS & LUBRICANTS
            // ══════════════════════════════════════════
            [
                'name' => 'Chemicals & Lubricants',
                'code' => 'CL',
                'slug' => 'chemicals-lubricants',
                'description' => 'Industrial chemicals, lubricants, solvents, and specialty fluids.',
                'sort_order' => 18,
                'children' => [
                    ['name' => 'Industrial Chemicals', 'slug' => 'industrial-chemicals', 'sort_order' => 1],
                    ['name' => 'Solvents & Thinners', 'slug' => 'solvents-thinners', 'sort_order' => 2],
                    ['name' => 'Lubricating Oils', 'slug' => 'lubricating-oils', 'sort_order' => 3],
                    ['name' => 'Greases', 'slug' => 'greases', 'sort_order' => 4],
                    ['name' => 'Coolants & Antifreeze', 'slug' => 'coolants-antifreeze', 'sort_order' => 5],
                    ['name' => 'Degreasers', 'slug' => 'degreasers', 'sort_order' => 6],
                    ['name' => 'Rust Treatments', 'slug' => 'rust-treatments', 'sort_order' => 7],
                    ['name' => 'Water Treatment Chemicals', 'slug' => 'water-treatment-chemicals', 'sort_order' => 8],
                ],
            ],

            // ══════════════════════════════════════════
            // 19. PRINTING & PROMOTIONAL
            // ══════════════════════════════════════════
            [
                'name' => 'Printing & Promotional',
                'code' => 'PP',
                'slug' => 'printing-promotional',
                'description' => 'Printing services, promotional products, custom merchandise, and signage.',
                'sort_order' => 19,
                'children' => [
                    ['name' => 'Business Cards & Stationery', 'slug' => 'business-cards-stationery', 'sort_order' => 1],
                    ['name' => 'Banners & Signs', 'slug' => 'banners-signs', 'sort_order' => 2],
                    ['name' => 'Promotional Products', 'slug' => 'promotional-products', 'sort_order' => 3],
                    ['name' => 'Custom Apparel', 'slug' => 'custom-apparel', 'sort_order' => 4],
                    ['name' => 'Brochures & Flyers', 'slug' => 'brochures-flyers', 'sort_order' => 5],
                    ['name' => 'Trophies & Awards', 'slug' => 'trophies-awards', 'sort_order' => 6],
                    ['name' => 'Exhibition & Trade Show', 'slug' => 'exhibition-trade-show', 'sort_order' => 7],
                ],
            ],

            // ══════════════════════════════════════════
            // 20. GARDENING & LANDSCAPING
            // ══════════════════════════════════════════
            [
                'name' => 'Gardening & Landscaping',
                'code' => 'GL',
                'slug' => 'gardening-landscaping',
                'description' => 'Garden tools, outdoor equipment, plants, and landscaping supplies.',
                'sort_order' => 20,
                'children' => [
                    ['name' => 'Garden Tools', 'slug' => 'garden-tools', 'sort_order' => 1],
                    ['name' => 'Lawn Mowers & Trimmers', 'slug' => 'lawn-mowers-trimmers', 'sort_order' => 2],
                    ['name' => 'Irrigation & Sprinklers', 'slug' => 'irrigation-sprinklers', 'sort_order' => 3],
                    ['name' => 'Plants & Seeds', 'slug' => 'plants-seeds', 'sort_order' => 4],
                    ['name' => 'Fertilizers & Soil', 'slug' => 'fertilizers-soil', 'sort_order' => 5],
                    ['name' => 'Pest Control (Garden)', 'slug' => 'pest-control-garden', 'sort_order' => 6],
                    ['name' => 'Outdoor Decor', 'slug' => 'outdoor-decor', 'sort_order' => 7],
                    ['name' => 'Pots & Planters', 'slug' => 'pots-planters', 'sort_order' => 8],
                    ['name' => 'Snow & Ice Removal', 'slug' => 'snow-ice-removal', 'sort_order' => 9],
                ],
            ],
        ];
    }
}
