<?php

namespace Tests\Feature\Profile;

use App\Actions\Profile\CreateProfileAction;
use App\Actions\Profile\DeleteProfileAction;
use App\Actions\Profile\UpdateProfileAction;

test('the Profile action classes exist', function () {
    expect(class_exists(CreateProfileAction::class))->toBeTrue();
    expect(class_exists(DeleteProfileAction::class))->toBeTrue();
    expect(class_exists(UpdateProfileAction::class))->toBeTrue();
});
