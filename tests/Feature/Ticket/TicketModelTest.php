<?php

namespace Tests\Feature\Ticket;

use App\Models\Ticket;
use Illuminate\Database\Eloquent\Model;

test('the Ticket model class exists', function () {
    expect(class_exists(Ticket::class))->toBeTrue();
    expect(is_subclass_of(Ticket::class, Model::class))->toBeTrue();
});
