<?php

namespace Tests\Unit\AuditLog;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;

test('the AuditLog model class exists', function () {
    expect(class_exists(AuditLog::class))->toBeTrue();
    expect(is_subclass_of(AuditLog::class, Model::class))->toBeTrue();
});
