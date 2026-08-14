<?php

namespace Tests\Unit\Profile;

test('the Profile service classes exist', function () {
    expect(class_exists(\App\Services\Profile\ProfileService::class))->toBeTrue();
});
