<?php

namespace Tests\Unit\Variant;

test('the Variant service classes exist', function () {
    expect(class_exists(\App\Services\Variant\VariantService::class))->toBeTrue();
});
