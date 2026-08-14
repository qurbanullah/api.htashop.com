<?php

namespace Tests\Unit\Online;

use App\Models\Online;
use Illuminate\Database\Eloquent\Model;

test('the Online model class exists', function () {
    expect(class_exists(Online::class))->toBeTrue();
    expect(is_subclass_of(Online::class, Model::class))->toBeTrue();
});
