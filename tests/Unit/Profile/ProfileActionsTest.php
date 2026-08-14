<?php

namespace Tests\Unit\Profile;

test('the Profile action classes exist', function () {
    expect(class_exists(\App\Actions\Profile\CreateProfileAction::class))->toBeTrue();
    expect(class_exists(\App\Actions\Profile\DeleteProfileAction::class))->toBeTrue();
    expect(class_exists(\App\Actions\Profile\UpdateProfileAction::class))->toBeTrue();
});
