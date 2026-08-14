<?php

namespace Tests\Unit\Message;

use App\Models\Message;
use Illuminate\Database\Eloquent\Model;

test('the Message model class exists', function () {
    expect(class_exists(Message::class))->toBeTrue();
    expect(is_subclass_of(Message::class, Model::class))->toBeTrue();
});
