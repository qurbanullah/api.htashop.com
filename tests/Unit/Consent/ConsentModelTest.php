<?php

namespace Tests\Unit\Consent;

use App\Models\Consent;
use Illuminate\Database\Eloquent\Model;

test('the Consent model class exists', function () {
    expect(class_exists(Consent::class))->toBeTrue();
    expect(is_subclass_of(Consent::class, Model::class))->toBeTrue();
});
