<?php

namespace Tests\Unit\Post;

test('the Post action classes exist', function () {
    expect(class_exists(\App\Actions\Post\CreatePostAction::class))->toBeTrue();
    expect(class_exists(\App\Actions\Post\DeletePostAction::class))->toBeTrue();
    expect(class_exists(\App\Actions\Post\SchedulePostAction::class))->toBeTrue();
    expect(class_exists(\App\Actions\Post\SendPostAction::class))->toBeTrue();
    expect(class_exists(\App\Actions\Post\UpdatePostAction::class))->toBeTrue();
});
