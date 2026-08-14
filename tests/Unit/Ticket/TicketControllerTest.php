<?php

namespace Tests\Unit\Ticket;

test('the Ticket controller class exists', function () {
    expect(class_exists(\App\Http\Controllers\V1\Support\PublicSupportTicketController::class))->toBeTrue();
    expect(class_exists(\App\Http\Controllers\V1\Ticket\TicketController::class))->toBeTrue();
});
