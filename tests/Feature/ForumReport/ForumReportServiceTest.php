<?php

namespace Tests\Feature\ForumReport;

test('the ForumReport service classes exist', function () {
    expect(class_exists(\App\Services\Forum\ForumReportService::class))->toBeTrue();
});
