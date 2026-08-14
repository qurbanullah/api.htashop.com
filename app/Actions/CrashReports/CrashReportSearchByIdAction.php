<?php

declare(strict_types=1);

namespace App\Actions\CrashReports;

use App\Models\CrashReport;

class CrashReportSearchByIdAction
{
    public function handle(int $id): ?CrashReport
    {
        return CrashReport::with(['assignedUser'])
            ->where('id', $id)
            ->first();
    }
}
