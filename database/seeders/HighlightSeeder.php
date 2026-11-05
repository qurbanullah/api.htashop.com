<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Highlight;
use Illuminate\Database\Seeder;

class HighlightSeeder extends Seeder
{
    public function run(): void
    {
        $highlights = $this->highlights();
        $idsBySlug = $this->categoryIdsBySlug($highlights);
        $count = 0;

        foreach ($highlights as $data) {
            $slugs = $data['categories'] ?? [];
            unset($data['categories']);

            // Re-sync category links on every run so seeder edits and schema
            // changes never leave stale categorizable rows behind.
            $highlight = Highlight::updateOrCreate(['code' => $data['code']], $data);

            $categoryIds = collect($slugs)
                ->map(fn (string $slug): ?int => $idsBySlug[$slug] ?? null)
                ->filter()
                ->unique()
                ->values()
                ->all();

            $highlight->categories()->sync($categoryIds);
            $count++;
        }

        $this->command?->info(sprintf(
            'Seeded %d highlights across %d category branches.',
            $count,
            count($idsBySlug),
        ));
    }

    /**
     * Resolve every referenced category slug once, warning about drift between
     * this file and {@see CategorySeeder}.
     *
     * @param  array<int, array<string, mixed>>  $highlights
     * @return array<string, int>
     */
    private function categoryIdsBySlug(array $highlights): array
    {
        $slugs = collect($highlights)
            ->flatMap(fn (array $highlight): array => $highlight['categories'] ?? [])
            ->unique()
            ->values();

        $idsBySlug = Category::query()->whereIn('slug', $slugs)->pluck('id', 'slug')->all();
        $unknown = $slugs->diff(array_keys($idsBySlug));

        if ($unknown->isNotEmpty()) {
            $this->command?->warn(
                'HighlightSeeder references unknown category slugs: '.$unknown->implode(', ')
            );
        }

        return $idsBySlug;
    }

    /**
     * Highlights are curated per top-level category branch: the manage picker
     * resolves any product category to its root, so these surface for every
     * product inside that branch.
     *
     * @return array<int, array<string, mixed>>
     */
    private function highlights(): array
    {
        return [
            // ── Electronics ──
            $this->highlight('ec-tested-tolerance', 'Tight Tolerance', 'Tight Tolerance', 'Verified against its published tolerance so circuits behave predictably.', 1, ['electronics']),
            $this->highlight('ec-datasheet-backed', 'Documented', 'Full Documentation', 'Datasheets, pinouts and application notes are available for engineering review.', 2, ['electronics']),
            $this->highlight('ec-esd-packaging', 'ESD Safe Packaging', 'ESD-Safe Packaging', 'Shipped in anti-static packaging to protect sensitive components in transit.', 3, ['electronics']),
            $this->highlight('ec-pre-soldered', 'Pre-Soldered', 'Pre-Soldered Headers', 'Boards arrive with headers fitted, so you can move straight to bench testing.', 4, ['electronics']),
            $this->highlight('ec-rohs', 'RoHS Compliant', 'RoHS Compliant', 'Restricted substances are kept out of every component, making it safe for regulated products.', 5, ['electronics']),

            // ── Information Technology ──
            $this->highlight('it-business-ready', 'Business Ready', 'Business Ready', 'Built for fleet deployment with manageability, security and warranty support.', 1, ['information-technology']),
            $this->highlight('it-fast-storage', 'Fast Storage', 'Fast Storage', 'Solid-state storage keeps boot times and large file transfers short.', 2, ['information-technology']),
            $this->highlight('it-secure-by-design', 'Secure by Design', 'Secure by Design', 'Hardware-backed security protects credentials and company data.', 3, ['information-technology']),
            $this->highlight('it-rich-connectivity', 'Rich Connectivity', 'Rich Connectivity', 'Modern ports let you connect displays, storage and peripherals without adapters.', 4, ['information-technology']),
            $this->highlight('it-energy-efficient', 'Energy Efficient', 'Energy Efficient', 'Low idle draw keeps running costs and heat output down in dense deployments.', 5, ['information-technology']),
            $this->highlight('it-warranty', 'Warranty Support', 'Warranty & Support', 'Covered by manufacturer warranty with local replacement options.', 6, ['information-technology']),

            // ── Office Supplies ──
            $this->highlight('os-everyday-reliable', 'Everyday Reliable', 'Everyday Reliable', 'Consistent performance for high-volume daily use.', 1, ['office-supplies']),
            $this->highlight('os-bulk-packed', 'Bulk Packed', 'Bulk Packed', 'Supplied in cost-effective multipacks to keep stockrooms topped up.', 2, ['office-supplies']),
            $this->highlight('os-acid-free', 'Acid Free', 'Archival Quality', 'Acid-free materials resist yellowing and stay legible over time.', 3, ['office-supplies']),
            $this->highlight('os-printer-compatible', 'Printer Compatible', 'Printer Compatible', 'Verified compatibility with common office printers and copiers.', 4, ['office-supplies']),
            $this->highlight('os-comfort-writing', 'Comfortable', 'Comfortable to Use', 'Balanced grip and smooth ink flow for extended writing sessions.', 5, ['office-supplies']),

            // ── Furniture ──
            $this->highlight('fu-ergonomic', 'Ergonomic', 'Ergonomic Design', 'Adjustable support that encourages healthy posture through the working day.', 1, ['furniture']),
            $this->highlight('fu-sturdy-frame', 'Sturdy Frame', 'Sturdy Frame', 'Reinforced frame and commercial-grade fixings for daily office use.', 2, ['furniture']),
            $this->highlight('fu-easy-assembly', 'Easy Assembly', 'Easy Assembly', 'Ships flat with clear instructions and all fixings included.', 3, ['furniture']),
            $this->highlight('fu-space-saving', 'Space Saving', 'Space Saving', 'Compact footprint keeps floor plans flexible in busy offices.', 4, ['furniture']),
            $this->highlight('fu-easy-clean', 'Easy Clean', 'Easy to Clean', 'Wipe-clean surfaces that hold up to everyday use.', 5, ['furniture']),

            // ── MRO & Industrial ──
            $this->highlight('mr-professional-grade', 'Professional Grade', 'Professional Grade', 'Built for continuous workshop and production-floor duty.', 1, ['mro-industrial']),
            $this->highlight('mr-precision-made', 'Precision Made', 'Precision Manufactured', 'Machined to tight tolerances for accurate, repeatable results.', 2, ['mro-industrial']),
            $this->highlight('mr-long-life', 'Long Service Life', 'Long Service Life', 'Wear-resistant materials extend the replacement interval.', 3, ['mro-industrial']),
            $this->highlight('mr-safe-operation', 'Safe Operation', 'Safe Operation', 'Guards and controls are designed around operator safety.', 4, ['mro-industrial']),
            $this->highlight('mr-ready-to-use', 'Ready to Use', 'Ready to Use', 'Arrives assembled and adjusted for immediate use.', 5, ['mro-industrial']),

            // ── Safety & PPE ──
            $this->highlight('sp-certified', 'Certified Protection', 'Certified Protection', 'Tested and certified to the relevant protective-equipment standards.', 1, ['safety-ppe']),
            $this->highlight('sp-all-day-comfort', 'All-Day Comfort', 'All-Day Comfort', 'Lightweight construction designed for full-shift wear.', 2, ['safety-ppe']),
            $this->highlight('sp-secure-fit', 'Secure Fit', 'Secure Fit', 'Adjustable fit keeps protection in place during active work.', 3, ['safety-ppe']),
            $this->highlight('sp-durable', 'Durable', 'Durable Materials', 'Resists abrasion, tearing and everyday workplace wear.', 4, ['safety-ppe']),
            $this->highlight('sp-compliance', 'Compliance', 'Workplace Compliance', 'Helps sites meet their mandatory PPE requirements.', 5, ['safety-ppe']),

            // ── Cleaning & Janitorial ──
            $this->highlight('cj-powerful-formula', 'Powerful Formula', 'Powerful Formula', 'Cuts through grease and grime with less scrubbing.', 1, ['cleaning-janitorial']),
            $this->highlight('cj-surface-safe', 'Surface Safe', 'Safe on Surfaces', 'Cleans effectively without damaging common commercial surfaces.', 2, ['cleaning-janitorial']),
            $this->highlight('cj-concentrate', 'Concentrated', 'Economical Concentrate', 'Dilutes to make more cleaning solution per container.', 3, ['cleaning-janitorial']),
            $this->highlight('cj-fragrance', 'Fresh Scent', 'Pleasant Fragrance', 'Leaves a fresh finish without overpowering the space.', 4, ['cleaning-janitorial']),
            $this->highlight('cj-professional-results', 'Professional Results', 'Professional Results', 'Delivers the standard expected in commercial environments.', 5, ['cleaning-janitorial']),

            // ── Packaging & Shipping ──
            $this->highlight('ps-strong-protection', 'Strong Protection', 'Strong Protection', 'Durable construction protects contents through handling and transit.', 1, ['packaging-shipping']),
            $this->highlight('ps-right-sized', 'Right Sized', 'Right-Sized Options', 'Multiple sizes reduce void fill and unnecessary shipping cost.', 2, ['packaging-shipping']),
            $this->highlight('ps-fast-packing', 'Fast Packing', 'Fast Packing', 'Designed for quick assembly and sealing on busy packing lines.', 3, ['packaging-shipping']),
            $this->highlight('ps-recyclable', 'Recyclable', 'Recyclable', 'Made from recyclable materials to support sustainability targets.', 4, ['packaging-shipping']),
            $this->highlight('ps-stackable', 'Stackable', 'Stackable Storage', 'Stacks neatly to save warehouse space before use.', 5, ['packaging-shipping']),

            // ── Lab & Scientific ──
            $this->highlight('lb-accurate-results', 'Accurate Results', 'Accurate Results', 'Calibrated for dependable, reproducible measurements.', 1, ['lab-scientific']),
            $this->highlight('lb-lab-grade', 'Lab Grade', 'Lab-Grade Materials', 'Made from materials suited to laboratory use and repeated cleaning.', 2, ['lab-scientific']),
            $this->highlight('lb-clean-sterile', 'Clean & Sterile', 'Clean and Sterile', 'Supplied ready for contamination-sensitive work.', 3, ['lab-scientific']),
            $this->highlight('lb-easy-maintenance', 'Easy Maintenance', 'Easy Maintenance', 'Simple to disassemble, clean and service.', 4, ['lab-scientific']),
            $this->highlight('lb-standards', 'Standards Compliant', 'Standards Compliant', 'Meets the requirements of common laboratory standards.', 5, ['lab-scientific']),

            // ── Medical & Healthcare ──
            $this->highlight('md-clinical-grade', 'Clinical Grade', 'Clinical Grade', 'Manufactured to the standards required for clinical environments.', 1, ['medical-healthcare']),
            $this->highlight('md-single-use', 'Single Use', 'Single-Use Safe', 'Individually packaged to reduce cross-contamination risk.', 2, ['medical-healthcare']),
            $this->highlight('md-comfortable', 'Comfortable', 'Comfortable to Use', 'Designed for patient comfort and safe handling.', 3, ['medical-healthcare']),
            $this->highlight('md-sterility', 'Reliable Sterility', 'Reliable Sterility', 'Sterilisation is validated and documented for every batch.', 4, ['medical-healthcare']),
            $this->highlight('md-disposal', 'Safe Disposal', 'Easy Disposal', 'Designed for safe, compliant disposal after use.', 5, ['medical-healthcare']),

            // ── Food Service & Breakroom ──
            $this->highlight('fb-fresh-quality', 'Fresh Quality', 'Fresh Quality', 'Sealed for freshness with clear best-before labelling.', 1, ['food-beverage']),
            $this->highlight('fb-catering-ready', 'Catering Ready', 'Catering Ready', 'Pack sizes suited to offices, canteens and catering operations.', 2, ['food-beverage']),
            $this->highlight('fb-food-safe', 'Food Safe', 'Food-Safe Materials', 'Materials approved for direct contact with food.', 3, ['food-beverage']),
            $this->highlight('fb-easy-storage', 'Easy Storage', 'Easy Storage', 'Shelf-stable packaging that fits standard storerooms.', 4, ['food-beverage']),
            $this->highlight('fb-wide-choice', 'Wide Choice', 'Wide Choice', 'A dependable range for everyday breakroom stock.', 5, ['food-beverage']),

            // ── Electrical & Lighting ──
            $this->highlight('el-energy-efficient', 'Energy Efficient', 'Energy Efficient', 'Delivers high output for low power consumption.', 1, ['electrical-lighting']),
            $this->highlight('el-simple-install', 'Simple Install', 'Simple Installation', 'Standard fittings and clear instructions speed up installation.', 2, ['electrical-lighting']),
            $this->highlight('el-long-life', 'Long Life', 'Long Life', 'Rated for extended operating hours before replacement.', 3, ['electrical-lighting']),
            $this->highlight('el-safety-certified', 'Safety Certified', 'Safety Certified', 'Certified to applicable electrical safety standards.', 4, ['electrical-lighting']),
            $this->highlight('el-consistent-output', 'Consistent Output', 'Consistent Output', 'Stable performance across voltage and temperature variation.', 5, ['electrical-lighting']),

            // ── Plumbing & HVAC ──
            $this->highlight('pl-leak-free', 'Leak Free', 'Leak-Free Connection', 'Precision threads and seals deliver dependable joints.', 1, ['plumbing-hvac']),
            $this->highlight('pl-durable', 'Durable', 'Durable Materials', 'Resists corrosion and scaling in demanding systems.', 2, ['plumbing-hvac']),
            $this->highlight('pl-easy-fit', 'Easy Fit', 'Easy to Fit', 'Designed for straightforward installation and servicing.', 3, ['plumbing-hvac']),
            $this->highlight('pl-efficient', 'Efficient', 'Efficient Performance', 'Optimised flow and heat transfer lower running costs.', 4, ['plumbing-hvac']),
            $this->highlight('pl-code-compliant', 'Code Compliant', 'Code Compliant', 'Manufactured to recognised plumbing and HVAC standards.', 5, ['plumbing-hvac']),

            // ── Building & Construction ──
            $this->highlight('bc-strong-durable', 'Strong & Durable', 'Strong and Durable', 'Built to withstand structural and site loads.', 1, ['building-construction']),
            $this->highlight('bc-weather-resistant', 'Weather Resistant', 'Weather Resistant', 'Holds up to moisture, UV and temperature cycling.', 2, ['building-construction']),
            $this->highlight('bc-consistent-quality', 'Consistent Quality', 'Consistent Quality', 'Uniform dimensions and finish across batches.', 3, ['building-construction']),
            $this->highlight('bc-site-ready', 'Site Ready', 'Site Ready', 'Finished and packed for straightforward installation.', 4, ['building-construction']),
            $this->highlight('bc-sustainable', 'Sustainably Sourced', 'Sustainably Sourced', 'Sourced from responsibly managed supply chains.', 5, ['building-construction']),

            // ── Automotive & Fleet ──
            $this->highlight('af-oem-compatible', 'OEM Compatible', 'OEM Compatible', 'Direct-fit replacement that meets original specifications.', 1, ['automotive-fleet']),
            $this->highlight('af-heavy-duty', 'Heavy Duty', 'Heavy Duty', 'Built for demanding fleet and commercial duty cycles.', 2, ['automotive-fleet']),
            $this->highlight('af-tested-reliability', 'Tested Reliability', 'Tested Reliability', 'Validated for performance and durability before release.', 3, ['automotive-fleet']),
            $this->highlight('af-easy-install', 'Easy Install', 'Easy Installation', 'Fits with standard tools and requires no modification.', 4, ['automotive-fleet']),
            $this->highlight('af-fleet-value', 'Fleet Value', 'Fleet Value', 'Cost-effective service life that lowers cost per mile.', 5, ['automotive-fleet']),

            // ── Textiles, Linens & Apparel ──
            $this->highlight('ta-comfortable-fit', 'Comfortable Fit', 'Comfortable Fit', 'Soft, breathable fabric for all-day wear.', 1, ['textiles-apparel']),
            $this->highlight('ta-durable-stitching', 'Durable Stitching', 'Durable Stitching', 'Reinforced seams withstand frequent washing.', 2, ['textiles-apparel']),
            $this->highlight('ta-colour-fast', 'Colour Fast', 'Colour Fast', 'Retains colour and shape after repeated laundering.', 3, ['textiles-apparel']),
            $this->highlight('ta-easy-care', 'Easy Care', 'Easy Care', 'Machine washable and quick drying.', 4, ['textiles-apparel']),
            $this->highlight('ta-professional-look', 'Professional Look', 'Professional Look', 'Neat finish suitable for uniform and corporate use.', 5, ['textiles-apparel']),

            // ── Sports & Recreation ──
            $this->highlight('sr-built-to-last', 'Built to Last', 'Built to Last', 'Tough materials for repeated indoor and outdoor use.', 1, ['sports-recreation']),
            $this->highlight('sr-comfortable', 'Comfortable', 'Comfortable to Use', 'Designed around ergonomics and safe use.', 2, ['sports-recreation']),
            $this->highlight('sr-portable', 'Portable', 'Easy to Move', 'Lightweight and compact for transport and storage.', 3, ['sports-recreation']),
            $this->highlight('sr-safe-design', 'Safe Design', 'Safe Design', 'Meets relevant safety standards for recreational equipment.', 4, ['sports-recreation']),
            $this->highlight('sr-all-levels', 'All Levels', 'For All Levels', 'Suits beginners and experienced users alike.', 5, ['sports-recreation']),

            // ── Educational Supplies ──
            $this->highlight('ed-classroom-ready', 'Classroom Ready', 'Classroom Ready', 'Pack sizes and materials suited to group teaching.', 1, ['educational-supplies']),
            $this->highlight('ed-child-safe', 'Child Safe', 'Child Safe', 'Non-toxic, rounded and tested for use by children.', 2, ['educational-supplies']),
            $this->highlight('ed-curriculum', 'Curriculum Aligned', 'Curriculum Aligned', 'Supports standard science, maths and STEM topics.', 3, ['educational-supplies']),
            $this->highlight('ed-durable-reusable', 'Durable & Reusable', 'Durable and Reusable', 'Withstands repeated handling across school years.', 4, ['educational-supplies']),
            $this->highlight('ed-engaging', 'Engaging', 'Engaging', 'Designed to keep learners focused and hands-on.', 5, ['educational-supplies']),

            // ── Chemicals & Lubricants ──
            $this->highlight('cl-proven-formula', 'Proven Formula', 'Proven Formula', 'Formulated for consistent, predictable performance.', 1, ['chemicals-lubricants']),
            $this->highlight('cl-safe-handling', 'Safe Handling', 'Safe Handling', 'Clear labelling and documented safety data for every batch.', 2, ['chemicals-lubricants']),
            $this->highlight('cl-long-lasting', 'Long Lasting', 'Long Lasting', 'Reduces reapplication and maintenance intervals.', 3, ['chemicals-lubricants']),
            $this->highlight('cl-material-compatible', 'Material Compatible', 'Material Compatible', 'Safe on the metals, seals and surfaces it is designed for.', 4, ['chemicals-lubricants']),
            $this->highlight('cl-sds-support', 'SDS Support', 'Full SDS Support', 'Safety data sheets available for compliance records.', 5, ['chemicals-lubricants']),

            // ── Printing & Promotional ──
            $this->highlight('pp-professional-finish', 'Professional Finish', 'Professional Finish', 'Crisp print and clean edges that reflect your brand.', 1, ['printing-promotional']),
            $this->highlight('pp-custom-design', 'Customisable', 'Fully Customisable', 'Artwork and sizes tailored to your requirements.', 2, ['printing-promotional']),
            $this->highlight('pp-fast-turnaround', 'Fast Turnaround', 'Fast Turnaround', 'Proofed and produced quickly for tight deadlines.', 3, ['printing-promotional']),
            $this->highlight('pp-bulk-pricing', 'Bulk Pricing', 'Bulk Pricing', 'Better rates as order quantities increase.', 4, ['printing-promotional']),
            $this->highlight('pp-durable-stock', 'Durable Stock', 'Durable Stock', 'Quality materials that hold up in daily use.', 5, ['printing-promotional']),

            // ── Gardening & Landscaping ──
            $this->highlight('gl-outdoor-ready', 'Outdoor Ready', 'Outdoor Ready', 'Built for weather, soil and everyday garden use.', 1, ['gardening-landscaping']),
            $this->highlight('gl-easy-maintenance', 'Easy Maintenance', 'Easy Maintenance', 'Simple to use, clean and store.', 2, ['gardening-landscaping']),
            $this->highlight('gl-durable-build', 'Durable Build', 'Durable Build', 'Rust-resistant and UV-stable materials.', 3, ['gardening-landscaping']),
            $this->highlight('gl-water-efficient', 'Water Efficient', 'Water Efficient', 'Designed to reduce water use without sacrificing results.', 4, ['gardening-landscaping']),
            $this->highlight('gl-seasonal-reliable', 'Seasonal Reliability', 'Seasonal Reliability', 'Performs through the full growing season.', 5, ['gardening-landscaping']),

            // ── Robotics & Automation ──
            $this->highlight('rb-ready-to-code', 'Ready to Code', 'Ready to Code', 'Well-documented libraries and examples get projects moving quickly.', 1, ['robotics-automation']),
            $this->highlight('rb-modular', 'Modular', 'Modular Design', 'Compatible mounting and connectors make upgrades simple.', 2, ['robotics-automation']),
            $this->highlight('rb-precise-control', 'Precise Control', 'Precise Control', 'Accurate positioning and repeatable motion for automation tasks.', 3, ['robotics-automation']),
            $this->highlight('rb-built-for-learning', 'Built for Learning', 'Built for Learning', 'Suited to classrooms, labs and engineering prototyping.', 4, ['robotics-automation']),
            $this->highlight('rb-reliable-hardware', 'Reliable Hardware', 'Reliable Hardware', 'Tested components that stand up to repeated operation.', 5, ['robotics-automation']),
        ];
    }

    /**
     * @param  array<int, string>  $categories
     * @return array<string, mixed>
     */
    private function highlight(
        string $code,
        string $label,
        string $heading,
        string $body,
        int $sortOrder,
        array $categories,
    ): array {
        return [
            'code' => $code,
            'label' => $label,
            'heading' => $heading,
            'body' => $body,
            'sort_order' => $sortOrder,
            'categories' => $categories,
        ];
    }
}
