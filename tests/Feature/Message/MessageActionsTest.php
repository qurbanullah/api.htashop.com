<?php

namespace Tests\Feature\Message;

use App\Actions\Contacts\SubmitContactMessageAction;
use App\Actions\Messages\MessageCreateAction;
use App\Actions\Messages\MessageDeleteAction;
use App\Actions\Messages\MessagePatchAction;
use App\Actions\Messages\MessageReadAction;
use App\Actions\Messages\MessageSearchByIdAction;
use App\Actions\Messages\MessageShowAction;
use App\Actions\Messages\MessageSortAction;
use App\Actions\Messages\MessageUpdateAction;

test('the Message action classes exist', function () {
    expect(class_exists(SubmitContactMessageAction::class))->toBeTrue();
    expect(class_exists(MessageCreateAction::class))->toBeTrue();
    expect(class_exists(MessageDeleteAction::class))->toBeTrue();
    expect(class_exists(MessagePatchAction::class))->toBeTrue();
    expect(class_exists(MessageReadAction::class))->toBeTrue();
    expect(class_exists(MessageSearchByIdAction::class))->toBeTrue();
    expect(class_exists(MessageShowAction::class))->toBeTrue();
    expect(class_exists(MessageSortAction::class))->toBeTrue();
    expect(class_exists(MessageUpdateAction::class))->toBeTrue();
});
