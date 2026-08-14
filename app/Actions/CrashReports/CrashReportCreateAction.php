<?php

declare(strict_types=1);

namespace App\Actions\CrashReports;

use App\Models\CrashReport;

class CrashReportCreateAction
{
    public function handle(array $data): CrashReport
    {
        return CrashReport::create($data);
    }
}
