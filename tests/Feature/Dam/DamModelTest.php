<?php

namespace Tests\Feature\Dam;

use App\Models\Dam;
use Illuminate\Database\Eloquent\Model;

test('the Dam model class exists', function () {
    expect(class_exists(Dam::class))->toBeTrue();
    expect(is_subclass_of(Dam::class, Model::class))->toBeTrue();
});
