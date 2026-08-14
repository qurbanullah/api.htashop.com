<?php

namespace Tests\Unit\DeletedUser;

use App\Models\DeletedUser;
use Illuminate\Database\Eloquent\Model;

test('the DeletedUser model class exists', function () {
    expect(class_exists(DeletedUser::class))->toBeTrue();
    expect(is_subclass_of(DeletedUser::class, Model::class))->toBeTrue();
});
