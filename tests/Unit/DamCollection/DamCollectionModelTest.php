<?php

namespace Tests\Unit\DamCollection;

use App\Models\DamCollection;
use Illuminate\Database\Eloquent\Model;

test('the DamCollection model class exists', function () {
    expect(class_exists(DamCollection::class))->toBeTrue();
    expect(is_subclass_of(DamCollection::class, Model::class))->toBeTrue();
});
