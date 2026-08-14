<?php

declare(strict_types=1);

namespace App\Actions\CrashReports;

use App\Models\CrashReport;

class CrashReportDeleteAction
{
    public function handle(int $id): bool
    {
        $crashReport = CrashReport::findOrFail($id);

        return $crashReport->delete();
    }
}
