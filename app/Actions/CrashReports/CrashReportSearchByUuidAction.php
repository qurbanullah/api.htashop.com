<?php

declare(strict_types=1);

namespace App\Actions\CrashReports;

use App\Models\CrashReport;

class CrashReportSearchByUuidAction
{
    public function handle(string $uuid): ?CrashReport
    {
        return CrashReport::with(['assignedUser'])
            ->where('uuid', $uuid)
            ->first();
    }
}
