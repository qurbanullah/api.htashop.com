<?php

use App\Models\DutyRate;
use App\Services\Pricing\DutyRateService;

it('normalizes HS codes to their digits', function () {
    expect(DutyRateService::normalize('8501.31.00'))->toBe('85013100')
        ->and(DutyRateService::normalize('8807'))->toBe('8807')
        ->and(DutyRateService::normalize('85-01'))->toBe('8501')
        ->and(DutyRateService::normalize(''))->toBe('');
});

it('matches the most specific rate by prefix', function () {
    DutyRate::query()->create(['hs_code' => '8501', 'description' => 'motors', 'customs_duty' => 10]);
    DutyRate::query()->create(['hs_code' => '8501.10', 'description' => 'micro motors', 'customs_duty' => 5]);

    $service = app(DutyRateService::class);

    expect((float) $service->rateFor('8501.31.00')?->customs_duty)->toBe(10.0)
        ->and((float) $service->rateFor('8501.10.90')?->customs_duty)->toBe(5.0);
});

it('returns null when no rate matches', function () {
    DutyRate::query()->create(['hs_code' => '8807', 'customs_duty' => 10]);

    expect(app(DutyRateService::class)->rateFor('9999'))->toBeNull();
});

it('ignores inactive rates', function () {
    DutyRate::query()->create(['hs_code' => '8807', 'customs_duty' => 10, 'is_active' => false]);

    expect(app(DutyRateService::class)->rateFor('8807'))->toBeNull();
});

it('estimates the total as a percentage of the amount', function () {
    DutyRate::query()->create(['hs_code' => '8807', 'customs_duty' => 10]);

    $service = app(DutyRateService::class);

    expect($service->estimate(1000, '8807.30'))->toBe(100.0)
        ->and($service->estimate(1000, '9999'))->toBe(0.0)
        ->and($service->estimate(250, null))->toBe(0.0);
});

it('breaks the import cost down into its components', function () {
    DutyRate::query()->create([
        'hs_code' => '8807',
        'customs_duty' => 10,
        'additional_customs_duty' => 2,
        'regulatory_duty' => 1,
        'sales_tax' => 17,
    ]);

    $breakdown = app(DutyRateService::class)->breakdown(1000, '8807.30');

    // CD = 100, ACD = 20, RD = 10; taxable = 1000+100+20+10 = 1130; ST = 192.10.
    expect($breakdown['customs_duty'])->toBe(100.0)
        ->and($breakdown['additional_customs_duty'])->toBe(20.0)
        ->and($breakdown['regulatory_duty'])->toBe(10.0)
        ->and($breakdown['sales_tax'])->toBe(192.10)
        ->and($breakdown['total'])->toBe(322.10)
        ->and($breakdown['china_fta_applied'])->toBeFalse();
});

it('applies the China FTA preference for China-origin goods', function () {
    DutyRate::query()->create([
        'hs_code' => '8501',
        'customs_duty' => 10,
        'china_fta_customs_duty' => 0,
    ]);

    $service = app(DutyRateService::class);

    expect($service->estimate(1000, '8501.31', 'CN'))->toBe(0.0)
        ->and($service->estimate(1000, '8501.31', 'US'))->toBe(100.0)
        ->and($service->breakdown(1000, '8501.31', 'CN')['china_fta_applied'])->toBeTrue();
});

it('falls back to the MFN rate when the FTA does not apply', function () {
    DutyRate::query()->create(['hs_code' => '8501', 'customs_duty' => 10, 'china_fta_customs_duty' => null]);

    expect(app(DutyRateService::class)->estimate(1000, '8501.31', 'CN'))->toBe(100.0);
});
