<?php

declare(strict_types=1);

namespace App\Actions\CrashReports;

use App\Models\CrashReport;

class CrashReportUpdateAction
{
    public function handle(array $data, int $id): CrashReport
    {
        $crashReport = CrashReport::findOrFail($id);
        $crashReport->update($data);

        return $crashReport->fresh();
    }
}
