<?php

namespace Tests\Feature\Package;

test('the Package controller class exists', function () {
    expect(class_exists(\App\Http\Controllers\V1\Package\PackageController::class))->toBeTrue();
});
