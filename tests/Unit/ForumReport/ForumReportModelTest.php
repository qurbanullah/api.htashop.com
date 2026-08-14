<?php

namespace Tests\Unit\ForumReport;

use App\Models\ForumReport;
use Illuminate\Database\Eloquent\Model;

test('the ForumReport model class exists', function () {
    expect(class_exists(ForumReport::class))->toBeTrue();
    expect(is_subclass_of(ForumReport::class, Model::class))->toBeTrue();
});
