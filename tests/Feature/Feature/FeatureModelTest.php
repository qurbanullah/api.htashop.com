<?php

namespace Tests\Feature\Feature;

use App\Models\Feature;
use Illuminate\Database\Eloquent\Model;

test('the Feature model class exists', function () {
    expect(class_exists(Feature::class))->toBeTrue();
    expect(is_subclass_of(Feature::class, Model::class))->toBeTrue();
});
