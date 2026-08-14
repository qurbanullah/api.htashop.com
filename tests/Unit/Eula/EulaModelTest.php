<?php

namespace Tests\Unit\Eula;

use App\Models\Eula;
use Illuminate\Database\Eloquent\Model;

test('the Eula model class exists', function () {
    expect(class_exists(Eula::class))->toBeTrue();
    expect(is_subclass_of(Eula::class, Model::class))->toBeTrue();
});
