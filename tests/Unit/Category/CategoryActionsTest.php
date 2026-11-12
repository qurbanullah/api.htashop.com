<?php

namespace Tests\Unit\Category;

use App\Actions\Tickets\TicketCategoryStatsAction;

test('the Category action classes exist', function () {
    expect(class_exists(TicketCategoryStatsAction::class))->toBeTrue();
});
