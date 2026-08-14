<?php

namespace Tests\Feature\Language;

use App\Models\Language;
use Illuminate\Database\Eloquent\Model;

test('the Language model class exists', function () {
    expect(class_exists(Language::class))->toBeTrue();
    expect(is_subclass_of(Language::class, Model::class))->toBeTrue();
});
