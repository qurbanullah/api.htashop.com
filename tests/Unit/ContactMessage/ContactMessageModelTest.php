<?php

namespace Tests\Unit\ContactMessage;

use App\Models\ContactMessage;
use Illuminate\Database\Eloquent\Model;

test('the ContactMessage model class exists', function () {
    expect(class_exists(ContactMessage::class))->toBeTrue();
    expect(is_subclass_of(ContactMessage::class, Model::class))->toBeTrue();
});
