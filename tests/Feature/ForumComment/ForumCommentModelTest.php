<?php

namespace Tests\Feature\ForumComment;

use App\Models\ForumComment;
use Illuminate\Database\Eloquent\Model;

test('the ForumComment model class exists', function () {
    expect(class_exists(ForumComment::class))->toBeTrue();
    expect(is_subclass_of(ForumComment::class, Model::class))->toBeTrue();
});
