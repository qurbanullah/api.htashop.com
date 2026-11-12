<?php

namespace Tests\Unit\DamCollection;

use App\Services\Dam\DamCollectionService;

test('the DamCollection service classes exist', function () {
    expect(class_exists(DamCollectionService::class))->toBeTrue();
});
