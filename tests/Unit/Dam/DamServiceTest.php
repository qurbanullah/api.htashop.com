<?php

namespace Tests\Unit\Dam;

use App\Services\Dam\DamCollectionService;
use App\Services\Dam\DamService;

test('the Dam service classes exist', function () {
    expect(class_exists(DamCollectionService::class))->toBeTrue();
    expect(class_exists(DamService::class))->toBeTrue();
});
