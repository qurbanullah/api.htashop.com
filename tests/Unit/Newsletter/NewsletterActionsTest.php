<?php

namespace Tests\Unit\Newsletter;

test('the Newsletter action classes exist', function () {
    expect(class_exists(\App\Actions\Newsletter\CreateNewsletterAction::class))->toBeTrue();
    expect(class_exists(\App\Actions\Newsletter\DeleteNewsletterAction::class))->toBeTrue();
    expect(class_exists(\App\Actions\Newsletter\ScheduleNewsletterAction::class))->toBeTrue();
    expect(class_exists(\App\Actions\Newsletter\SendNewsletterAction::class))->toBeTrue();
    expect(class_exists(\App\Actions\Newsletter\UpdateNewsletterAction::class))->toBeTrue();
});
