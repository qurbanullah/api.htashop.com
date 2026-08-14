<?php

namespace Tests\Unit\Permission;

use App\Models\Permission;
use Illuminate\Database\Eloquent\Model;

test('the Permission model class exists', function () {
    expect(class_exists(Permission::class))->toBeTrue();
    expect(is_subclass_of(Permission::class, Model::class))->toBeTrue();
});
