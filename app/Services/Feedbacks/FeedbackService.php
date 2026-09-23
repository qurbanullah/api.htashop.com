<?php

namespace App\Services\Feedbacks;

use App\Actions\Feedbacks\FeedbackAddCommentAction;
use App\Actions\Feedbacks\FeedbackApprovedFeaturesAction;
use App\Actions\Feedbacks\FeedbackCreateAction;
use App\Actions\Feedbacks\FeedbackGetCommentsAction;
use App\Enums\FeedbackStatusEnum;
use App\Helpers\CacheHelper;
use App\Jobs\Feedback\SendFeedbackCommentEmailJob;
use App\Models\Comment;
use App\Models\Feedback;
use App\Support\Tenant\TenantContext;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Pipeline;

class FeedbackService
{
    /**
     * Create feedback
     */
    public function create(array $data): Feedback
    {
        // UUID is auto-generated in model boot method
        $feedback = (new FeedbackCreateAction())->handle($data);

        CacheHelper::clearTags(['support']);

        return $feedback;
    }

    /**
     * Get feedback with filters and pagination (tenant-scoped)
     */
    public function show(array $data = []): LengthAwarePaginator
    {
        return Pipeline::send($this->visibleQuery())
            ->through(
                [
                    new \App\Filters\Feedbacks\FeedbackSearchFilter(data_get($data, 'filters.search')),
                    new \App\Filters\Feedbacks\FeedbackTypeFilter(data_get($data, 'filters.type')),
                    new \App\Filters\Feedbacks\FeedbackStatusFilter(data_get($data, 'filters.status')),
                    new \App\Filters\Feedbacks\FeedbackPriorityFilter(data_get($data, 'filters.priority')),
                ]
            )
            ->thenReturn()
            ->latest()
            ->paginate(data_get($data, 'rows', 15));
    }

    /**
     * Get approved feature requests with filters using optimized Action
     */
    public function getApprovedFeatures(array $data = []): LengthAwarePaginator
    {
        return (new FeedbackApprovedFeaturesAction())->handle($data);
    }

    /**
     * Find feedback by UUID (tenant-scoped)
     */
    public function findByUuid(string $uuid): ?Feedback
    {
        return $this->visibleQuery()->where('feedbacks.uuid', $uuid)->first();
    }

    /**
     * Update feedback (tenant-scoped)
     */
    public function update(int $feedbackId, array $data): bool
    {
        $feedback = $this->visibleQuery()->find($feedbackId);

        if (!$feedback) {
            return false;
        }

        $updated = $feedback->update($data);

        if ($updated) {
            CacheHelper::clearTags(['support']);
        }

        return $updated;
    }

    /**
     * Delete feedback (tenant-scoped)
     */
    public function delete(int $feedbackId): bool
    {
        $feedback = $this->visibleQuery()->find($feedbackId);

        if (!$feedback) {
            return false;
        }

        $deleted = $feedback->delete();

        if ($deleted) {
            CacheHelper::clearTags(['support']);
        }

        return $deleted;
    }

    /**
     * Search and filter feedback
     */
    public function search(?string $search = null, ?string $status = null, ?string $type = null): Builder
    {
        $query = Feedback::query()
            ->with('repliedBy')
            ->orderBy('created_at', 'desc');

        if ($search) {
            $query->search($search);
        }

        if ($status) {
            $query->byStatus($status);
        }

        if ($type) {
            $query->byType($type);
        }

        return $query;
    }

    /**
     * Get feedback statistics (tenant-scoped, cached)
     */
    public function getStatistics(): array
    {
        return $this->cachedStatistics('general', function () {
            $base = $this->applyTenantScope(Feedback::query());

            $total = (clone $base)->count();
            $new = (clone $base)->where('status', FeedbackStatusEnum::NEW->value)->count();
            $replied = (clone $base)->where('status', FeedbackStatusEnum::REPLIED->value)->count();
            $pending = (clone $base)->whereIn('status', [
                FeedbackStatusEnum::NEW->value,
                FeedbackStatusEnum::READ->value,
            ])->count();

            return [
                'total' => $total,
                'new' => $new,
                'replied' => $replied,
                'pending' => $pending,
                'new_percentage' => $total > 0 ? round(($new / $total) * 100, 1) : 0,
                'replied_percentage' => $total > 0 ? round(($replied / $total) * 100, 1) : 0,
                'pending_percentage' => $total > 0 ? round(($pending / $total) * 100, 1) : 0,
            ];
        });
    }

    /**
     * Get feedback statistics by type (tenant-scoped, cached)
     */
    public function getTypeStatistics(): array
    {
        return $this->cachedStatistics('by_type', function () {
            return $this->applyTenantScope(Feedback::query())
                ->selectRaw('type, count(*) as count')
                ->groupBy('type')
                ->pluck('count', 'type')
                ->toArray();
        });
    }

    /**
     * Get feedback statistics by priority (tenant-scoped, cached)
     */
    public function getPriorityStatistics(): array
    {
        return $this->cachedStatistics('by_priority', function () {
            return $this->applyTenantScope(Feedback::query())
                ->selectRaw('priority, count(*) as count')
                ->groupBy('priority')
                ->pluck('count', 'priority')
                ->toArray();
        });
    }

    /**
     * Mark feedback as read (tenant-scoped)
     */
    public function markAsRead(int $feedbackId): bool
    {
        $feedback = $this->visibleQuery()->find($feedbackId);

        if (!$feedback || $feedback->status !== FeedbackStatusEnum::NEW->value) {
            return false;
        }

        $feedback->markAsRead();

        CacheHelper::clearTags(['support']);

        return true;
    }

    /**
     * Mark feedback as replied (tenant-scoped)
     */
    public function markAsReplied(int $feedbackId, string $response, int $repliedBy): bool
    {
        $feedback = $this->visibleQuery()->find($feedbackId);

        if (!$feedback) {
            return false;
        }

        $feedback->markAsReplied($response, $repliedBy);

        CacheHelper::clearTags(['support']);

        return true;
    }

    /**
     * Get recent feedback (last 7 days)
     */
    public function getRecentFeedback(int $limit = 10): Collection
    {
        return Feedback::with('repliedBy')
            ->where('created_at', '>=', now()->subDays(7))
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get high priority feedback
     */
    public function getHighPriorityFeedback(): Collection
    {
        return Feedback::with('repliedBy')
            ->whereIn('priority', ['high', 'critical'])
            ->whereIn('status', ['new', 'read'])
            ->orderBy('priority', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Get user feedbacks (for dashboard)
     */
    public function getUserFeedbacks(?string $userEmail = null, array $filters = [], int $limit = 15): mixed
    {
        $email = $userEmail ?? (Auth::check() ? Auth::user()->email : null);
        if (!$email) {
            return Feedback::query()->paginate($limit);
        }

        $data = [
            'filters' => array_merge($filters, ['email' => $email]),
            'rows' => $limit,
        ];

        return $this->show($data);
    }

    /**
     * Add comment/reply to feedback
     * Business Logic: Create comment, send email, clear cache
     *
     * @param Feedback $feedback
     * @param array $commentData
     * @return Comment
     * @throws \Exception
     */
    public function addCommentToFeedback(Feedback $feedback, array $commentData): Comment
    {
        try {
            // Step 1: Create comment using Action (CRUD only)
            $comment = (new FeedbackAddCommentAction())->handle($feedback, $commentData);

            // Step 2: Dispatch email notification job (non-blocking)
            SendFeedbackCommentEmailJob::dispatch($feedback, $comment);

            // Step 3: Update feedback status if needed
            if ($feedback->status === 'new') {
                $feedback->update(['status' => 'replied']);
            }

            // Step 4: Clear relevant caches
            CacheHelper::forget([], "feedback.{$feedback->uuid}");
            CacheHelper::forget([], "feedback.{$feedback->id}.comments");
            CacheHelper::clearTags(['support']);

            // Step 5: Log the action
            Log::info('Comment added to feedback', [
                'feedback_id' => $feedback->id,
                'feedback_uuid' => $feedback->uuid,
                'comment_id' => $comment->id,
                'user_id' => $commentData['user_id'],
                'is_internal' => $comment->is_internal,
            ]);

            return $comment;

        } catch (\Exception $e) {
            Log::error('Failed to add comment to feedback', [
                'feedback_id' => $feedback->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * Get comments for feedback
     *
     * @param Feedback $feedback
     * @param bool $includeInternal
     * @return Collection
     */
    public function getFeedbackComments(Feedback $feedback, bool $includeInternal = true): Collection
    {
        return (new FeedbackGetCommentsAction())->handle($feedback, $includeInternal);
    }

    /**
     * @deprecated Use getFeedbackComments() instead
     * Legacy method for backward compatibility
     */
    public function getComments(int $feedbackId): array
    {
        $feedback = Feedback::find($feedbackId);
        if (!$feedback) {
            return [];
        }

        // Return old JSON format from additional_info if exists
        return $feedback?->additional_info['comments'] ?? [];
    }

    /**
     * Check if feedback is approved (for public display)
     */
    public function isApproved(Feedback $feedback): bool
    {
        return $feedback->type === 'feature_request' && $feedback->status === 'replied';
    }

    /**
     * Generate UUID for feedback if not exists
     */
    public function ensureUuid(Feedback $feedback): Feedback
    {
        if (empty($feedback->uuid)) {
            $feedback->update(['uuid' => (string) \Illuminate\Support\Str::uuid()]);
        }
        return $feedback;
    }

    /**
     * Base feedback query scoped to the current admin's tenant and eager
     * loading the admin who replied.
     *
     * - super-admin or unauthenticated -> global (no filter)
     * - tenant-bound admin -> only that tenant's rows
     * - no active membership -> no rows at all
     */
    private function visibleQuery(): Builder
    {
        return $this->applyTenantScope(Feedback::query()->with('repliedBy'));
    }

    /**
     * Apply the resolved admin tenant scope to a feedback query.
     */
    private function applyTenantScope(Builder $query): Builder
    {
        $scope = TenantContext::adminScope();

        if ($scope === 0) {
            $query->whereRaw('1 = 0');
        } elseif ($scope !== null) {
            $query->where('feedbacks.tenant_id', $scope);
        }

        return $query;
    }

    /**
     * Resolve tenant-scoped feedback statistics through the support cache.
     * Scope 0 (user with no active membership) never touches the cache.
     *
     * @param string $kind general|by_type|by_priority
     */
    private function cachedStatistics(string $kind, Closure $compute): array
    {
        $scope = TenantContext::adminScope();

        if ($scope === 0) {
            return $compute();
        }

        $scopeKey = $scope === null ? 'all' : 'tenant:' . $scope;

        return CacheHelper::remember(
            ['support'],
            "support:feedback:stats:{$kind}:{$scopeKey}",
            60,
            $compute
        );
    }
}
