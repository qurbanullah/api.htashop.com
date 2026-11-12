<?php

namespace Tests\Unit\Role;

use App\Actions\Users\AssignRolesAction;

test('the Role action classes exist', function () {
    expect(class_exists(AssignRolesAction::class))->toBeTrue();
});
