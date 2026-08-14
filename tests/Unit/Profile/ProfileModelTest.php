<?php

namespace Tests\Unit\Profile;

use App\Models\Profile;
use Illuminate\Database\Eloquent\Model;

test('the Profile model class exists', function () {
    expect(class_exists(Profile::class))->toBeTrue();
    expect(is_subclass_of(Profile::class, Model::class))->toBeTrue();
});
