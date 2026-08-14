<?php

namespace Tests\Unit\Helpers;

test('global helper functions are available', function () {
    expect(function_exists('safe_user_name'))->toBeTrue();
    expect(function_exists('safe_user_email'))->toBeTrue();
    expect(function_exists('safe_user_display'))->toBeTrue();
    expect(function_exists('get_user_with_trashed'))->toBeTrue();
    expect(function_exists('get_user_or_deleted_record'))->toBeTrue();
    expect(function_exists('permanently_delete_user'))->toBeTrue();
    expect(function_exists('soft_delete_user_with_audit'))->toBeTrue();
    expect(function_exists('restore_user_with_audit'))->toBeTrue();
    expect(function_exists('is_user_permanently_deleted'))->toBeTrue();
});
