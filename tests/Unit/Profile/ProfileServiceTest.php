<?php

namespace Tests\Unit\Profile;

use App\Services\Profile\ProfileService;

test('the Profile service classes exist', function () {
    expect(class_exists(ProfileService::class))->toBeTrue();
});
