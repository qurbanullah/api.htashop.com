<?php

namespace Tests\Unit\EmailLog;

use App\Models\EmailLog;
use Illuminate\Database\Eloquent\Model;

test('the EmailLog model class exists', function () {
    expect(class_exists(EmailLog::class))->toBeTrue();
    expect(is_subclass_of(EmailLog::class, Model::class))->toBeTrue();
});
