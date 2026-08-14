<?php

namespace Tests\Unit\Helpers;

use App\Helpers\CacheHelper;

test('the CacheHelper class exists', function () {
    expect(class_exists(CacheHelper::class))->toBeTrue();
});
