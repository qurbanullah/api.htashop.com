<?php

namespace Tests\Unit\Avatar;

use App\Models\Avatar;
use Illuminate\Database\Eloquent\Model;

test('the Avatar model class exists', function () {
    expect(class_exists(Avatar::class))->toBeTrue();
    expect(is_subclass_of(Avatar::class, Model::class))->toBeTrue();
});
