<?php

namespace Tests\Feature\DefinitionTarget;

use App\Models\DefinitionTarget;
use Illuminate\Database\Eloquent\Model;

test('the DefinitionTarget model class exists', function () {
    expect(class_exists(DefinitionTarget::class))->toBeTrue();
    expect(is_subclass_of(DefinitionTarget::class, Model::class))->toBeTrue();
});
