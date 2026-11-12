<?php

namespace Tests\Unit\User;

use App\Services\User\UserService;

test('the User service classes exist', function () {
    expect(class_exists(UserService::class))->toBeTrue();
    expect(class_exists(\App\Services\Users\UserService::class))->toBeTrue();
});
