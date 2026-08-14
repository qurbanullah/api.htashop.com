<?php

namespace Tests\Feature\Category;

test('the Category action classes exist', function () {
    expect(class_exists(\App\Actions\Tickets\TicketCategoryStatsAction::class))->toBeTrue();
});
