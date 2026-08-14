<?php

namespace Tests\Feature\Tag;

use App\Models\Tag;
use Illuminate\Database\Eloquent\Model;

test('the Tag model class exists', function () {
    expect(class_exists(Tag::class))->toBeTrue();
    expect(is_subclass_of(Tag::class, Model::class))->toBeTrue();
});
