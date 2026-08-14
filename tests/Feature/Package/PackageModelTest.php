<?php

namespace Tests\Feature\Package;

use App\Models\Package;
use Illuminate\Database\Eloquent\Model;

test('the Package model class exists', function () {
    expect(class_exists(Package::class))->toBeTrue();
    expect(is_subclass_of(Package::class, Model::class))->toBeTrue();
});
