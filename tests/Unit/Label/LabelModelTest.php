<?php

namespace Tests\Unit\Label;

use App\Models\Label;
use Illuminate\Database\Eloquent\Model;

test('the Label model class exists', function () {
    expect(class_exists(Label::class))->toBeTrue();
    expect(is_subclass_of(Label::class, Model::class))->toBeTrue();
});
