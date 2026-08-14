<?php

namespace Tests\Unit\ForumReport;

test('the ForumReport service classes exist', function () {
    expect(class_exists(\App\Services\Forum\ForumReportService::class))->toBeTrue();
});
