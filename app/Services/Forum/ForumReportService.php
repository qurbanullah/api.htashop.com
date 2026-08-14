<?php

namespace App\Services\Forum;

use App\Enums\ForumReportStatusEnum;
use App\Models\ForumComment;
use App\Models\ForumPost;
use App\Models\ForumReport;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\DB;

class ForumReportService
{
    public function createReport(User $user, string $type, int $id, array $data): ForumReport
    {
        $reportable = $this->resolveReportable($type, $id);
        $reportableType = $reportable::class;

        // Prevent duplicate reports by same user on same content
        $existing = ForumReport::where('user_id', $user->id)
            ->where('reportable_type', $reportableType)
            ->where('reportable_id', $reportable->getKey())
            ->where('status', ForumReportStatusEnum::PENDING->value)
            ->first();

        if ($existing) {
            return $existing;
        }

        return ForumReport::create([
            'user_id' => $user->id,
            'reportable_type' => $reportableType,
            'reportable_id' => $reportable->getKey(),
            'reason' => $data['reason'],
            'description' => $data['description'] ?? null,
        ]);
    }

    public function getReports(array $filters = []): LengthAwarePaginator
    {
        $query = ForumReport::query()
            ->with([
                'reporter:id,name,email',
                'reviewer:id,name,email',
                'reportable',
            ]);

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['reason'])) {
            $query->where('reason', $filters['reason']);
        }

        if (!empty($filters['reportable_type'])) {
            $type = match ($filters['reportable_type']) {
                'post' => ForumPost::class,
                'comment' => ForumComment::class,
                default => null,
            };
            if ($type) {
                $query->where('reportable_type', $type);
            }
        }

        $query->orderByDesc('created_at');

        $perPage = min($filters['per_page'] ?? 15, 50);

        return $query->paginate($perPage);
    }

    public function reviewReport(ForumReport $report, User $admin, array $data): ForumReport
    {
        DB::transaction(function () use ($report, $admin, $data) {
            $report->update([
                'status' => $data['status'],
                'reviewed_by' => $admin->id,
                'reviewed_at' => now(),
                'admin_notes' => $data['admin_notes'] ?? null,
            ]);
        });

        return $report->fresh(['reporter:id,name,email', 'reviewer:id,name,email']);
    }

    public function getStats(): array
    {
        return [
            'total_reports' => ForumReport::count(),
            'pending_reports' => ForumReport::pending()->count(),
            'reviewed_reports' => ForumReport::where('status', ForumReportStatusEnum::REVIEWED->value)->count(),
            'dismissed_reports' => ForumReport::where('status', ForumReportStatusEnum::DISMISSED->value)->count(),
        ];
    }

    private function resolveReportable(string $type, int $id): Model
    {
        return match ($type) {
            'post' => ForumPost::query()
                ->whereKey($id)
                ->where('status', 'published')
                ->first(),
            'comment' => ForumComment::query()
                ->whereKey($id)
                ->where('status', 'visible')
                ->whereHas('post', function ($query) {
                    $query->where('status', 'published');
                })
                ->first(),
            default => throw ValidationException::withMessages([
                'reportable_type' => ['Report type must be either "post" or "comment".'],
            ]),
        } ?? throw ValidationException::withMessages([
            'reportable_id' => ['The selected content could not be found.'],
        ]);
    }
}
