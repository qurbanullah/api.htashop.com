<?php

namespace Tests\Feature\Tenant;

use App\Actions\Punchout\ResolvePunchoutTenantAction;

test('the Tenant action classes exist', function () {
    expect(class_exists(ResolvePunchoutTenantAction::class))->toBeTrue();
});
