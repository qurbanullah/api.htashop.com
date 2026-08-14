<?php

namespace Tests\Feature\Option;

use App\Models\Option;
use Illuminate\Database\Eloquent\Model;

test('the Option model class exists', function () {
    expect(class_exists(Option::class))->toBeTrue();
    expect(is_subclass_of(Option::class, Model::class))->toBeTrue();
});
