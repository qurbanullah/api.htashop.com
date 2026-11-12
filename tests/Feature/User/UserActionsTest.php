<?php

namespace Tests\Feature\User;

use App\Actions\Tickets\TicketUserStatsAction;
use App\Actions\Users\CreateUserAction;
use App\Actions\Users\DeleteUserAction;
use App\Actions\Users\RestoreUserAction;
use App\Actions\Users\UpdateUserAction;
use App\Actions\Users\UserPatchAction;
use App\Actions\Users\UserSearchByIdAction;
use App\Actions\Users\UserSearchBySlugAction;
use App\Actions\Users\UserShowAction;
use App\Actions\Users\UserUpdateAction;

test('the User action classes exist', function () {
    expect(class_exists(TicketUserStatsAction::class))->toBeTrue();
    expect(class_exists(CreateUserAction::class))->toBeTrue();
    expect(class_exists(DeleteUserAction::class))->toBeTrue();
    expect(class_exists(RestoreUserAction::class))->toBeTrue();
    expect(class_exists(UpdateUserAction::class))->toBeTrue();
    expect(class_exists(UserPatchAction::class))->toBeTrue();
    expect(class_exists(UserSearchByIdAction::class))->toBeTrue();
    expect(class_exists(UserSearchBySlugAction::class))->toBeTrue();
    expect(class_exists(UserShowAction::class))->toBeTrue();
    expect(class_exists(UserUpdateAction::class))->toBeTrue();
});
