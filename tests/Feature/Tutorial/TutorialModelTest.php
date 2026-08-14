<?php

namespace Tests\Feature\Tutorial;

use App\Models\Tutorial;
use Illuminate\Database\Eloquent\Model;

test('the Tutorial model class exists', function () {
    expect(class_exists(Tutorial::class))->toBeTrue();
    expect(is_subclass_of(Tutorial::class, Model::class))->toBeTrue();
});
