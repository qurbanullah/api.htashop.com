<?php

namespace Tests\Unit\Helpers;

use App\Helpers\AdminHelper;

test('the AdminHelper class exists', function () {
    expect(class_exists(AdminHelper::class))->toBeTrue();
});
