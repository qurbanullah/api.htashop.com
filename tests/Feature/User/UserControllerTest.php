<?php

namespace Tests\Feature\User;

use App\Http\Controllers\V1\User\UserController;

test('the User controller class exists', function () {
    expect(class_exists(UserController::class))->toBeTrue();
});
