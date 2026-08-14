<?php

namespace Tests\Feature\ForumTopic;

use App\Models\ForumTopic;
use Illuminate\Database\Eloquent\Model;

test('the ForumTopic model class exists', function () {
    expect(class_exists(ForumTopic::class))->toBeTrue();
    expect(is_subclass_of(ForumTopic::class, Model::class))->toBeTrue();
});
