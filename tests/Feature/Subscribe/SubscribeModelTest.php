<?php

namespace Tests\Feature\Subscribe;

use App\Models\Subscribe;
use Illuminate\Database\Eloquent\Model;

test('the Subscribe model class exists', function () {
    expect(class_exists(Subscribe::class))->toBeTrue();
    expect(is_subclass_of(Subscribe::class, Model::class))->toBeTrue();
});
