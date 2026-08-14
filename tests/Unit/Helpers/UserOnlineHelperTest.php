<?php

namespace Tests\Unit\Helpers;

use App\Helpers\UserOnlineHelper;

test('the UserOnlineHelper class exists', function () {
    expect(class_exists(UserOnlineHelper::class))->toBeTrue();
});
