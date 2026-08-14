<?php

namespace Tests\Unit\ForumPost;

use App\Models\ForumPost;
use Illuminate\Database\Eloquent\Model;

test('the ForumPost model class exists', function () {
    expect(class_exists(ForumPost::class))->toBeTrue();
    expect(is_subclass_of(ForumPost::class, Model::class))->toBeTrue();
});
