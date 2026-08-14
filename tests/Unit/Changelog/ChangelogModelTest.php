<?php

namespace Tests\Unit\Changelog;

use App\Models\Changelog;
use Illuminate\Database\Eloquent\Model;

test('the Changelog model class exists', function () {
    expect(class_exists(Changelog::class))->toBeTrue();
    expect(is_subclass_of(Changelog::class, Model::class))->toBeTrue();
});
