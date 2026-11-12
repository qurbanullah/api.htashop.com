<?php

namespace Tests\Unit\Package;

use App\Services\Packages\PackageService;

test('the Package service classes exist', function () {
    expect(class_exists(PackageService::class))->toBeTrue();
});
