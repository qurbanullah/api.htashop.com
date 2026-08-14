<?php

namespace Tests\Feature\Unit;

use App\Models\Unit;
use Illuminate\Database\Eloquent\Model;

test('the Unit model class exists', function () {
    expect(class_exists(Unit::class))->toBeTrue();
    expect(is_subclass_of(Unit::class, Model::class))->toBeTrue();
});
