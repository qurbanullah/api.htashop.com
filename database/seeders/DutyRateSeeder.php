<?php

namespace Database\Seeders;

use App\Models\DutyRate;
use Illuminate\Database\Seeder;

class DutyRateSeeder extends Seeder
{
    /**
     * Seed Pakistan import duty & tax rates for robotics / drone components.
     *
     * ⚠️ The percentages below are ILLUSTRATIVE PLACEHOLDERS for the "Import on
     * Demand" landed-cost estimate. `additional_customs_duty` (2) and
     * `sales_tax` (17) are applied uniformly as illustrative defaults; the
     * China–FTA figures are also illustrative. Verify every figure against the
     * current Pakistan Customs PCT schedule (WeBOC / PSW) — including SRO-driven
     * regulatory duty — before going live. Rates change over time.
     */
    public function run(): void
    {
        foreach ($this->rates() as $data) {
            DutyRate::updateOrCreate(
                ['hs_code' => $data['hs_code']],
                $data
            );
        }

        $this->command->info('Duty rates seeded: '.count($this->rates()).' headings.');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function rates(): array
    {
        return [
            // UAV / drone parts and accessories (assembled aircraft excluded).
            $this->duty('8807', 'Parts of aircraft / drones (frames, props, gimbals)', 10, 0),
            // Electric motors — the propulsion and actuation workhorses.
            $this->duty('8501', 'Electric motors & generators (BLDC, stepper, servo)', 10, 0),
            $this->duty('8501.10', 'Electric motors ≤ 37.5 W (micro servos)', 5, 0),
            // Batteries.
            $this->duty('8507', 'Electric accumulators (Li-ion / LiPo packs)', 20, 10),
            $this->duty('8507.60', 'Lithium-ion accumulators', 20, 10),
            // Navigation / control electronics.
            $this->duty('8526', 'Radar / radio-navigation apparatus (GPS, flight controllers)', 10, 0),
            $this->duty('8529', 'Parts for radio/radar apparatus (antennas, modules)', 10, 0),
            $this->duty('8525', 'Cameras & transmission apparatus (FPV, multispectral)', 10, 0),
            // Semiconductors and boards.
            $this->duty('8542', 'Electronic integrated circuits (MCUs, sensors)', 5, 0),
            $this->duty('8473.30', 'Parts of computers (controller boards, add-on modules)', 10, 0),
            // Instruments and control gear.
            $this->duty('9014', 'Navigational instruments (gyros, compasses)', 5, 0),
            $this->duty('8536', 'Electrical switching apparatus (connectors, relays)', 15, 5),
            $this->duty('8537', 'Control panels & PLCs', 10, 0),
            // Plastics — a catch-all for structural/printed parts.
            $this->duty('3926', 'Other articles of plastics (enclosures, propellers)', 15, 5),
            // Educational robots often classify as toys (FTA may not apply).
            $this->duty('9503', 'Toys & educational robots', 20, null),
            // Additional headings used by the catalog (added to cover the
            // full 90-product launch list).
            $this->duty('8471.50', 'Processing units / single-board computers (Raspberry Pi)', 10, 0),
            $this->duty('8504', 'Chargers & power converters', 10, 0),
            $this->duty('8413', 'Pumps', 10, 5),
            $this->duty('8424', 'Mechanical spraying appliances (tanks, nozzles)', 10, 5),
            $this->duty('8428', 'Lifting/handling machinery (robots & cobots)', 10, 5),
            $this->duty('8544', 'Insulated wire & cable', 10, 5),
            $this->duty('7616', 'Other articles of aluminium (standoffs, brackets)', 10, 5),
            $this->duty('9026', 'Flow & measuring instruments', 5, 0),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function duty(
        string $hsCode,
        string $description,
        float $customsDuty,
        ?float $chinaFtaCustomsDuty
    ): array {
        return [
            'hs_code' => $hsCode,
            'description' => $description,
            'customs_duty' => $customsDuty,
            'additional_customs_duty' => 2,
            'regulatory_duty' => 0,
            'sales_tax' => 17,
            'china_fta_customs_duty' => $chinaFtaCustomsDuty,
        ];
    }
}
