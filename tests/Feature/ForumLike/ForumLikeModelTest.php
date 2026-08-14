<?php

namespace Tests\Feature\ForumLike;

use App\Models\ForumLike;
use Illuminate\Database\Eloquent\Model;

test('the ForumLike model class exists', function () {
    expect(class_exists(ForumLike::class))->toBeTrue();
    expect(is_subclass_of(ForumLike::class, Model::class))->toBeTrue();
});
