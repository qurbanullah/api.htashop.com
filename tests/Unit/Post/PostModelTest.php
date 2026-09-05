<?php

namespace Tests\Unit\Post;

use App\Models\Post;
use Illuminate\Database\Eloquent\Model;

test('the Post model class exists', function () {
    expect(class_exists(Post::class))->toBeTrue();
    expect(is_subclass_of(Post::class, Model::class))->toBeTrue();
});
