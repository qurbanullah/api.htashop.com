<?php

namespace Tests\Feature\Email;

use App\Models\Email;
use Illuminate\Database\Eloquent\Model;

test('the Email model class exists', function () {
    expect(class_exists(Email::class))->toBeTrue();
    expect(is_subclass_of(Email::class, Model::class))->toBeTrue();
});
