<?php

namespace Tests\Unit\Price;

use App\Models\Price;
use Illuminate\Database\Eloquent\Model;

test('the Price model class exists', function () {
    expect(class_exists(Price::class))->toBeTrue();
    expect(is_subclass_of(Price::class, Model::class))->toBeTrue();
});
