<?php

namespace Tests\Unit\Ticket;

use App\Services\Tickets\TicketService;

test('the Ticket service classes exist', function () {
    expect(class_exists(TicketService::class))->toBeTrue();
});
