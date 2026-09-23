<?php

namespace Tests\Feature\ContactMessage;

test('the ContactMessage action classes exist', function () {
    expect(class_exists(\App\Actions\Contacts\SubmitContactMessageAction::class))->toBeTrue();
});
