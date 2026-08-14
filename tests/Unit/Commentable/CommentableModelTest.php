<?php

namespace Tests\Unit\Commentable;

use App\Models\Commentable;
use Illuminate\Database\Eloquent\Model;

test('the Commentable model class exists', function () {
    expect(class_exists(Commentable::class))->toBeTrue();
    expect(is_subclass_of(Commentable::class, Model::class))->toBeTrue();
});
