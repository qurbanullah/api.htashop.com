<?php

namespace Tests\Feature\Role;

use App\Models\Role;
use Illuminate\Database\Eloquent\Model;

test('the Role model class exists', function () {
    expect(class_exists(Role::class))->toBeTrue();
    expect(is_subclass_of(Role::class, Model::class))->toBeTrue();
});
