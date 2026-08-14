<?php

namespace Tests\Feature\Variant;

use App\Models\Variant;
use Illuminate\Database\Eloquent\Model;

test('the Variant model class exists', function () {
    expect(class_exists(Variant::class))->toBeTrue();
    expect(is_subclass_of(Variant::class, Model::class))->toBeTrue();
});
