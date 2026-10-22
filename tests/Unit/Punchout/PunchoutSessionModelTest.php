<?php

namespace Tests\Unit\PunchoutSession;

use App\Models\PunchoutSession;
use Illuminate\Database\Eloquent\Model;

test('the PunchoutSession model class exists', function () {
    expect(class_exists(PunchoutSession::class))->toBeTrue();
    expect(is_subclass_of(PunchoutSession::class, Model::class))->toBeTrue();
});
