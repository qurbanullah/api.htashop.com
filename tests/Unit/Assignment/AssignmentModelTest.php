<?php

namespace Tests\Unit\Assignment;

use App\Models\Assignment;
use Illuminate\Database\Eloquent\Model;

test('the Assignment model class exists', function () {
    expect(class_exists(Assignment::class))->toBeTrue();
    expect(is_subclass_of(Assignment::class, Model::class))->toBeTrue();
});
