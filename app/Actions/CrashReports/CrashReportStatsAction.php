<?php

declare(strict_types=1);

namespace App\Actions\CrashReports;

use App\Models\CrashReport;
use Illuminate\Support\Facades\DB;

class CrashReportStatsAction
{
    public function handle(): array
    {
        return [
            'total' => CrashReport::count(),
            'unresolved' => CrashReport::where('is_resolved', false)->count(),
            'resolved' => CrashReport::where('is_resolved', true)->count(),
            'by_severity' => $this->getBySeverity(),
            'by_status' => $this->getByStatus(),
            'by_crash_type' => $this->getByCrashType(),
            'recent_count' => CrashReport::where('created_at', '>=', now()->subDays(7))->count(),
        ];
    }

    private function getBySeverity(): array
    {
        return CrashReport::select('severity', DB::raw('count(*) as count'))
            ->groupBy('severity')
            ->pluck('count', 'severity')
            ->toArray();
    }

    private function getByStatus(): array
    {
        return CrashReport::select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();
    }

    private function getByCrashType(): array
    {
        return CrashReport::select('crash_type', DB::raw('count(*) as count'))
            ->whereNotNull('crash_type')
            ->groupBy('crash_type')
            ->pluck('count', 'crash_type')
            ->toArray();
    }
}
