<?php

namespace Tests\Unit\Feedback;

use App\Models\Feedback;
use Illuminate\Database\Eloquent\Model;

test('the Feedback model class exists', function () {
    expect(class_exists(Feedback::class))->toBeTrue();
    expect(is_subclass_of(Feedback::class, Model::class))->toBeTrue();
});
