<?php

namespace Tests\Feature\Unit;

use App\Services\Unit\UnitService;

test('the Unit service classes exist', function () {
    expect(class_exists(UnitService::class))->toBeTrue();
});
