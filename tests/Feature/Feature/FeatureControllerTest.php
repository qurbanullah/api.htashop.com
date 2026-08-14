<?php

namespace Tests\Feature\Feature;

test('the Feature controller class exists', function () {
    expect(class_exists(\App\Http\Controllers\V1\Feature\FeatureController::class))->toBeTrue();
});
