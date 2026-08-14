<?php

namespace Tests\Feature\Translation;

use App\Models\Translation;
use Illuminate\Database\Eloquent\Model;

test('the Translation model class exists', function () {
    expect(class_exists(Translation::class))->toBeTrue();
    expect(is_subclass_of(Translation::class, Model::class))->toBeTrue();
});
