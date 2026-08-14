<?php

namespace Tests\Feature\Definition;

use App\Models\Definition;
use Illuminate\Database\Eloquent\Model;

test('the Definition model class exists', function () {
    expect(class_exists(Definition::class))->toBeTrue();
    expect(is_subclass_of(Definition::class, Model::class))->toBeTrue();
});
