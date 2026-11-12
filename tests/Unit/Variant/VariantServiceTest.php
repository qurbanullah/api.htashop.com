<?php

namespace Tests\Unit\Variant;

use App\Services\Variant\VariantService;

test('the Variant service classes exist', function () {
    expect(class_exists(VariantService::class))->toBeTrue();
});
