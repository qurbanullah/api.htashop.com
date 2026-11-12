<?php

namespace Tests\Unit\Profile;

use App\Http\Controllers\V1\Profile\ProfileController;

test('the Profile controller class exists', function () {
    expect(class_exists(ProfileController::class))->toBeTrue();
});
