<?php

namespace Tests\Unit\Helpers;

test('global helper functions are available', function () {
    expect(function_exists('is_user_online'))->toBeTrue();
    expect(function_exists('get_online_users_count'))->toBeTrue();
    expect(function_exists('get_online_users_count_direct'))->toBeTrue();
    expect(function_exists('get_online_user_ids'))->toBeTrue();
    expect(function_exists('are_users_online'))->toBeTrue();
});
