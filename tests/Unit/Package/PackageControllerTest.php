<?php

namespace Tests\Unit\Package;

use App\Http\Controllers\V1\Package\PackageController;

test('the Package controller class exists', function () {
    expect(class_exists(PackageController::class))->toBeTrue();
});
