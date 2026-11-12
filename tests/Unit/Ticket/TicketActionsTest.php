<?php

namespace Tests\Unit\Ticket;

use App\Actions\Tickets\TicketAllStatsAction;
use App\Actions\Tickets\TicketArchiveAction;
use App\Actions\Tickets\TicketCategoryStatsAction;
use App\Actions\Tickets\TicketCloseAction;
use App\Actions\Tickets\TicketCreateAction;
use App\Actions\Tickets\TicketDeleteAction;
use App\Actions\Tickets\TicketLockAction;
use App\Actions\Tickets\TicketPatchAction;
use App\Actions\Tickets\TicketPriorityStatsAction;
use App\Actions\Tickets\TicketReadAction;
use App\Actions\Tickets\TicketResolveAction;
use App\Actions\Tickets\TicketSearchByIdAction;
use App\Actions\Tickets\TicketSearchByUuidAction;
use App\Actions\Tickets\TicketShowAction;
use App\Actions\Tickets\TicketStatusStatsAction;
use App\Actions\Tickets\TicketTotalCountAction;
use App\Actions\Tickets\TicketUpdateAction;
use App\Actions\Tickets\TicketUserStatsAction;

test('the Ticket action classes exist', function () {
    expect(class_exists(TicketAllStatsAction::class))->toBeTrue();
    expect(class_exists(TicketArchiveAction::class))->toBeTrue();
    expect(class_exists(TicketCategoryStatsAction::class))->toBeTrue();
    expect(class_exists(TicketCloseAction::class))->toBeTrue();
    expect(class_exists(TicketCreateAction::class))->toBeTrue();
    expect(class_exists(TicketDeleteAction::class))->toBeTrue();
    expect(class_exists(TicketLockAction::class))->toBeTrue();
    expect(class_exists(TicketPatchAction::class))->toBeTrue();
    expect(class_exists(TicketPriorityStatsAction::class))->toBeTrue();
    expect(class_exists(TicketReadAction::class))->toBeTrue();
    expect(class_exists(TicketResolveAction::class))->toBeTrue();
    expect(class_exists(TicketSearchByIdAction::class))->toBeTrue();
    expect(class_exists(TicketSearchByUuidAction::class))->toBeTrue();
    expect(class_exists(TicketShowAction::class))->toBeTrue();
    expect(class_exists(TicketStatusStatsAction::class))->toBeTrue();
    expect(class_exists(TicketTotalCountAction::class))->toBeTrue();
    expect(class_exists(TicketUpdateAction::class))->toBeTrue();
    expect(class_exists(TicketUserStatsAction::class))->toBeTrue();
});
