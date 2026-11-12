<?php

namespace Tests\Feature\Variant;

use App\Http\Controllers\V1\Variant\VariantController;

test('the Variant controller class exists', function () {
    expect(class_exists(VariantController::class))->toBeTrue();
});
