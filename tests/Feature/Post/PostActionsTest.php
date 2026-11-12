<?php

namespace Tests\Feature\Post;

use App\Actions\Post\CreatePostAction;
use App\Actions\Post\DeletePostAction;
use App\Actions\Post\SchedulePostAction;
use App\Actions\Post\SendPostAction;
use App\Actions\Post\UpdatePostAction;

test('the Post action classes exist', function () {
    expect(class_exists(CreatePostAction::class))->toBeTrue();
    expect(class_exists(DeletePostAction::class))->toBeTrue();
    expect(class_exists(SchedulePostAction::class))->toBeTrue();
    expect(class_exists(SendPostAction::class))->toBeTrue();
    expect(class_exists(UpdatePostAction::class))->toBeTrue();
});
