<?php

namespace Tests\Unit\Measurement;

use App\Models\Measurement;
use Illuminate\Database\Eloquent\Model;

test('the Measurement model class exists', function () {
    expect(class_exists(Measurement::class))->toBeTrue();
    expect(is_subclass_of(Measurement::class, Model::class))->toBeTrue();
});
