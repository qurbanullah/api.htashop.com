<?php

namespace Tests\Unit\Comment;

use App\Models\Comment;
use Illuminate\Database\Eloquent\Model;

test('the Comment model class exists', function () {
    expect(class_exists(Comment::class))->toBeTrue();
    expect(is_subclass_of(Comment::class, Model::class))->toBeTrue();
});
