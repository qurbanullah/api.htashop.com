<?php

namespace Tests\Unit\Tenant;

use App\Actions\Punchout\ResolvePunchoutTenantAction;

test('the Tenant action classes exist', function () {
    expect(class_exists(ResolvePunchoutTenantAction::class))->toBeTrue();
});
