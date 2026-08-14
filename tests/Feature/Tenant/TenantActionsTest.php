<?php

namespace Tests\Feature\Tenant;

test('the Tenant action classes exist', function () {
    expect(class_exists(\App\Actions\Punchout\ResolvePunchoutTenantAction::class))->toBeTrue();
});
