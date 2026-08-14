<?php

namespace Tests\Unit\Profile;

test('the Profile controller class exists', function () {
    expect(class_exists(\App\Http\Controllers\V1\Profile\ProfileController::class))->toBeTrue();
});
