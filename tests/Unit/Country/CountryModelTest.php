<?php

namespace Tests\Unit\Country;

use App\Models\Country;
use Illuminate\Database\Eloquent\Model;

test('the Country model class exists', function () {
    expect(class_exists(Country::class))->toBeTrue();
    expect(is_subclass_of(Country::class, Model::class))->toBeTrue();
});
