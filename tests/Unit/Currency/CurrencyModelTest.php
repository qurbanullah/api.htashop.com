<?php

namespace Tests\Unit\Currency;

use App\Models\Currency;
use Illuminate\Database\Eloquent\Model;

test('the Currency model class exists', function () {
    expect(class_exists(Currency::class))->toBeTrue();
    expect(is_subclass_of(Currency::class, Model::class))->toBeTrue();
});
