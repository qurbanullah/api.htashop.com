<?php

namespace Tests\Feature\Catalog;

use App\Models\Catalog;
use Illuminate\Database\Eloquent\Model;

test('the Catalog model class exists', function () {
    expect(class_exists(Catalog::class))->toBeTrue();
    expect(is_subclass_of(Catalog::class, Model::class))->toBeTrue();
});
