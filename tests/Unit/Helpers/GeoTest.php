<?php

namespace Tests\Unit\Helpers;

use App\Helpers\Geo;

test('the Geo class exists', function () {
    expect(class_exists(Geo::class))->toBeTrue();
});
