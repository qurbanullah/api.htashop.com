<?php

namespace Tests\Unit\ForumReport;

use App\Services\Forum\ForumReportService;

test('the ForumReport service classes exist', function () {
    expect(class_exists(ForumReportService::class))->toBeTrue();
});
