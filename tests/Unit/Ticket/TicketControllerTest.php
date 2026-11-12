<?php

namespace Tests\Unit\Ticket;

use App\Http\Controllers\V1\Support\PublicSupportTicketController;
use App\Http\Controllers\V1\Ticket\TicketController;

test('the Ticket controller class exists', function () {
    expect(class_exists(PublicSupportTicketController::class))->toBeTrue();
    expect(class_exists(TicketController::class))->toBeTrue();
});
