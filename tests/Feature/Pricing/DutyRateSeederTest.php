<?php

use App\Models\DutyRate;
use Database\Seeders\DutyRateSeeder;

it('seeds the full robotics duty headings including the catalog additions', function () {
    $this->seed(DutyRateSeeder::class);

    $codes = DutyRate::query()->pluck('hs_code')->all();

    expect($codes)->toContain('8807')
        ->and($codes)->toContain('8501')
        ->and($codes)->toContain('8471.50')
        ->and($codes)->toContain('8424')
        ->and($codes)->toContain('8428')
        ->and($codes)->toContain('8544')
        ->and($codes)->toContain('7616');

    $charger = DutyRate::query()->where('hs_code', '8504')->first();
    expect($charger)->not->toBeNull()
        ->and((float) $charger->customs_duty)->toBe(10.0)
        ->and((float) $charger->additional_customs_duty)->toBe(2.0)
        ->and((float) $charger->sales_tax)->toBe(17.0);
});
