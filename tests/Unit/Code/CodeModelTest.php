<?php

namespace Tests\Unit\Code;

use App\Models\Code;
use Illuminate\Database\Eloquent\Model;

test('the Code model class exists', function () {
    expect(class_exists(Code::class))->toBeTrue();
    expect(is_subclass_of(Code::class, Model::class))->toBeTrue();
});
