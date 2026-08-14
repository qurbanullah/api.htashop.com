<?php

namespace Tests\Feature\User;

test('the User service classes exist', function () {
    expect(class_exists(\App\Services\User\UserService::class))->toBeTrue();
    expect(class_exists(\App\Services\Users\UserService::class))->toBeTrue();
});
