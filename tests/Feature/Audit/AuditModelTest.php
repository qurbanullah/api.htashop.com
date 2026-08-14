<?php

namespace Tests\Feature\Audit;

use App\Models\Audit;
use Illuminate\Database\Eloquent\Model;

test('the Audit model class exists', function () {
    expect(class_exists(Audit::class))->toBeTrue();
    expect(is_subclass_of(Audit::class, Model::class))->toBeTrue();
});
