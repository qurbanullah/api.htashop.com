<?php

namespace Tests\Feature\Feature;

use App\Http\Controllers\V1\Feature\FeatureController;

test('the Feature controller class exists', function () {
    expect(class_exists(FeatureController::class))->toBeTrue();
});
