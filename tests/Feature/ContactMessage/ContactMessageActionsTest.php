<?php

namespace Tests\Feature\ContactMessage;

use App\Actions\Contacts\SubmitContactMessageAction;

test('the ContactMessage action classes exist', function () {
    expect(class_exists(SubmitContactMessageAction::class))->toBeTrue();
});
