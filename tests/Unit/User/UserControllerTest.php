<?php

namespace Tests\Unit\User;

test('the User controller class exists', function () {
    expect(class_exists(\App\Http\Controllers\V1\User\UserController::class))->toBeTrue();
});
