<?php

namespace Tests\Unit\Newsletter;

use App\Models\Newsletter;
use Illuminate\Database\Eloquent\Model;

test('the Newsletter model class exists', function () {
    expect(class_exists(Newsletter::class))->toBeTrue();
    expect(is_subclass_of(Newsletter::class, Model::class))->toBeTrue();
});
