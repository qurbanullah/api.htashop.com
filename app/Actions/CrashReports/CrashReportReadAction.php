<?php

declare(strict_types=1);

namespace App\Actions\CrashReports;

use App\Models\CrashReport;
use Illuminate\Pagination\LengthAwarePaginator;

class CrashReportReadAction
{
    public function handle(array $input = []): LengthAwarePaginator
    {
        $query = CrashReport::query()->with(['assignedUser']);

        // Apply filters
        if (isset($input['filters'])) {
            $filters = $input['filters'];

            if (isset($filters['status'])) {
                $query->where('status', $filters['status']);
            }

            if (isset($filters['severity'])) {
                $query->where('severity', $filters['severity']);
            }

            if (isset($filters['crash_type'])) {
                $query->where('crash_type', $filters['crash_type']);
            }

            if (isset($filters['is_resolved'])) {
                $query->where('is_resolved', $filters['is_resolved']);
            }

            if (isset($filters['machine_id'])) {
                $query->where('machine_id', $filters['machine_id']);
            }

            if (isset($filters['assigned_to'])) {
                $query->where('assigned_to', $filters['assigned_to']);
            }

            if (isset($filters['app_name'])) {
                $query->where('app_name', $filters['app_name']);
            }
        }

        // Apply search
        if (isset($input['search']) && !empty($input['search'])) {
            $search = $input['search'];
            $query->where(function ($q) use ($search) {
                $q->where('machine_id', 'like', "%{$search}%")
                    ->orWhere('hostname', 'like', "%{$search}%")
                    ->orWhere('platform', 'like', "%{$search}%")
                    ->orWhere('error_message', 'like', "%{$search}%")
                    ->orWhere('app_version', 'like', "%{$search}%")
                    ->orWhere('app_name', 'like', "%{$search}%")
                    ->orWhere('file_name', 'like', "%{$search}%");
            });
        }

        // Apply sorting
        $sortBy = $input['sort_by'] ?? 'created_at';
        $sortOrder = $input['sort_order'] ?? 'desc';
        $query->orderBy($sortBy, $sortOrder);

        // Pagination
        $perPage = $input['per_page'] ?? 15;

        return $query->paginate($perPage);
    }
}
