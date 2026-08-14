<?php

namespace Tests\Unit\User;

test('the User action classes exist', function () {
    expect(class_exists(\App\Actions\Tickets\TicketUserStatsAction::class))->toBeTrue();
    expect(class_exists(\App\Actions\Users\CreateUserAction::class))->toBeTrue();
    expect(class_exists(\App\Actions\Users\DeleteUserAction::class))->toBeTrue();
    expect(class_exists(\App\Actions\Users\RestoreUserAction::class))->toBeTrue();
    expect(class_exists(\App\Actions\Users\UpdateUserAction::class))->toBeTrue();
    expect(class_exists(\App\Actions\Users\UserPatchAction::class))->toBeTrue();
    expect(class_exists(\App\Actions\Users\UserSearchByIdAction::class))->toBeTrue();
    expect(class_exists(\App\Actions\Users\UserSearchBySlugAction::class))->toBeTrue();
    expect(class_exists(\App\Actions\Users\UserShowAction::class))->toBeTrue();
    expect(class_exists(\App\Actions\Users\UserUpdateAction::class))->toBeTrue();
});
