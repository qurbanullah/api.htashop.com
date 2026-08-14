<?php

namespace Tests\Unit\Tenant;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Model;

test('the Tenant model class exists', function () {
    expect(class_exists(Tenant::class))->toBeTrue();
    expect(is_subclass_of(Tenant::class, Model::class))->toBeTrue();
});
