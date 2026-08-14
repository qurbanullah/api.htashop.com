<?php

namespace Tests\Feature\Ticket;

test('the Ticket action classes exist', function () {
    expect(class_exists(\App\Actions\Tickets\TicketAllStatsAction::class))->toBeTrue();
    expect(class_exists(\App\Actions\Tickets\TicketArchiveAction::class))->toBeTrue();
    expect(class_exists(\App\Actions\Tickets\TicketCategoryStatsAction::class))->toBeTrue();
    expect(class_exists(\App\Actions\Tickets\TicketCloseAction::class))->toBeTrue();
    expect(class_exists(\App\Actions\Tickets\TicketCreateAction::class))->toBeTrue();
    expect(class_exists(\App\Actions\Tickets\TicketDeleteAction::class))->toBeTrue();
    expect(class_exists(\App\Actions\Tickets\TicketLockAction::class))->toBeTrue();
    expect(class_exists(\App\Actions\Tickets\TicketPatchAction::class))->toBeTrue();
    expect(class_exists(\App\Actions\Tickets\TicketPriorityStatsAction::class))->toBeTrue();
    expect(class_exists(\App\Actions\Tickets\TicketReadAction::class))->toBeTrue();
    expect(class_exists(\App\Actions\Tickets\TicketResolveAction::class))->toBeTrue();
    expect(class_exists(\App\Actions\Tickets\TicketSearchByIdAction::class))->toBeTrue();
    expect(class_exists(\App\Actions\Tickets\TicketSearchByUuidAction::class))->toBeTrue();
    expect(class_exists(\App\Actions\Tickets\TicketShowAction::class))->toBeTrue();
    expect(class_exists(\App\Actions\Tickets\TicketStatusStatsAction::class))->toBeTrue();
    expect(class_exists(\App\Actions\Tickets\TicketTotalCountAction::class))->toBeTrue();
    expect(class_exists(\App\Actions\Tickets\TicketUpdateAction::class))->toBeTrue();
    expect(class_exists(\App\Actions\Tickets\TicketUserStatsAction::class))->toBeTrue();
});
