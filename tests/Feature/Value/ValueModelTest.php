<?php

namespace Tests\Feature\Value;

use App\Models\Value;
use Illuminate\Database\Eloquent\Model;

test('the Value model class exists', function () {
    expect(class_exists(Value::class))->toBeTrue();
    expect(is_subclass_of(Value::class, Model::class))->toBeTrue();
});
