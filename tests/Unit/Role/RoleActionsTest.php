<?php

namespace Tests\Unit\Role;

test('the Role action classes exist', function () {
    expect(class_exists(\App\Actions\Users\AssignRolesAction::class))->toBeTrue();
});
