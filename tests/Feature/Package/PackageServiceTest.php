<?php

namespace Tests\Feature\Package;

test('the Package service classes exist', function () {
    expect(class_exists(\App\Services\Packages\PackageService::class))->toBeTrue();
});
