<?php

namespace Tests\Unit\Subscription;

use App\Models\Subscription;
use Illuminate\Database\Eloquent\Model;

test('the Subscription model class exists', function () {
    expect(class_exists(Subscription::class))->toBeTrue();
    expect(is_subclass_of(Subscription::class, Model::class))->toBeTrue();
});
