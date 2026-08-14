<?php

namespace Tests\Unit\Ticket;

test('the Ticket service classes exist', function () {
    expect(class_exists(\App\Services\Tickets\TicketService::class))->toBeTrue();
});
