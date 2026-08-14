<?php

namespace Tests\Unit\User;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

test('the User model class exists', function () {
    expect(class_exists(User::class))->toBeTrue();
    expect(is_subclass_of(User::class, Model::class))->toBeTrue();
});
