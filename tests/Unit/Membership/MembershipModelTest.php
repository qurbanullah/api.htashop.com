<?php

namespace Tests\Unit\Membership;

use App\Models\Membership;
use Illuminate\Database\Eloquent\Model;

test('the Membership model class exists', function () {
    expect(class_exists(Membership::class))->toBeTrue();
    expect(is_subclass_of(Membership::class, Model::class))->toBeTrue();
});
