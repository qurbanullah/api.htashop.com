<?php

namespace Tests\Feature\Organization;

use App\Models\Organization;
use Illuminate\Database\Eloquent\Model;

test('the Organization model class exists', function () {
    expect(class_exists(Organization::class))->toBeTrue();
    expect(is_subclass_of(Organization::class, Model::class))->toBeTrue();
});
