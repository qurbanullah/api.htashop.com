<?php

namespace Tests\Unit\Contract;

use App\Models\Contract;
use Illuminate\Database\Eloquent\Model;

test('the Contract model class exists', function () {
    expect(class_exists(Contract::class))->toBeTrue();
    expect(is_subclass_of(Contract::class, Model::class))->toBeTrue();
});
