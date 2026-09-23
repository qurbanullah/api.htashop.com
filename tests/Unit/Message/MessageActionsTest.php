<?php

namespace Tests\Unit\Message;

test('the Message action classes exist', function () {
    expect(class_exists(\App\Actions\Contacts\SubmitContactMessageAction::class))->toBeTrue();
    expect(class_exists(\App\Actions\Messages\MessageCreateAction::class))->toBeTrue();
    expect(class_exists(\App\Actions\Messages\MessageDeleteAction::class))->toBeTrue();
    expect(class_exists(\App\Actions\Messages\MessagePatchAction::class))->toBeTrue();
    expect(class_exists(\App\Actions\Messages\MessageReadAction::class))->toBeTrue();
    expect(class_exists(\App\Actions\Messages\MessageSearchByIdAction::class))->toBeTrue();
    expect(class_exists(\App\Actions\Messages\MessageShowAction::class))->toBeTrue();
    expect(class_exists(\App\Actions\Messages\MessageSortAction::class))->toBeTrue();
    expect(class_exists(\App\Actions\Messages\MessageUpdateAction::class))->toBeTrue();
});
