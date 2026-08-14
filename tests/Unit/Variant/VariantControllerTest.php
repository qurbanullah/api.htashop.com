<?php

namespace Tests\Unit\Variant;

test('the Variant controller class exists', function () {
    expect(class_exists(\App\Http\Controllers\V1\Variant\VariantController::class))->toBeTrue();
});
