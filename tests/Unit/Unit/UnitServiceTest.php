<?php

namespace Tests\Unit\Unit;

test('the Unit service classes exist', function () {
    expect(class_exists(\App\Services\Unit\UnitService::class))->toBeTrue();
});
