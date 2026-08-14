<?php

namespace App\Services\Feedbacks;

use App\Helpers\CacheHelper;
use App\Actions\Feedbacks\FeedbackAddCommentAction;
use App\Actions\Feedbacks\FeedbackApprovedFeaturesAction;
use App\Actions\Feedbacks\FeedbackCreateAction;
use App\Actions\Feedbacks\FeedbackDeleteAction;
use App\Actions\Feedbacks\FeedbackGetCommentsAction;
use App\Actions\Feedbacks\FeedbackSearchByUuidAction;
use App\Actions\Feedbacks\FeedbackShowAction;
use App\Actions\Feedbacks\FeedbackUpdateAction;
use App\Jobs\Feedback\SendFeedbackCommentEmailJob;
use App\Models\Comment;
use App\Models\Feedback;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class FeedbackService
{
    /**
     * Create feedback
     */
    public function create(array $data): Feedback
    {
        // UUID is auto-generated in model boot method
        return (new FeedbackCreateAction())->handle($data);
    }

    /**
     * Get feedback with filters and pagination using optimized Action
     */
    public function show(array $data = []): LengthAwarePaginator
    {
        return (new FeedbackShowAction())->handle($data);
    }

    /**
     * Get approved feature requests with filters using optimized Action
     */
    public function getApprovedFeatures(array $data = []): LengthAwarePaginator
    {
        return (new FeedbackApprovedFeaturesAction())->handle($data);
    }

    /**
     * Find feedback by UUID
     */
    public function findByUuid(string $uuid): ?Feedback
    {
        return (new FeedbackSearchByUuidAction())->handle($uuid);
    }

    /**
     * Update feedback
     */
    public function update(int $feedbackId, array $data): bool
    {
        return (new FeedbackUpdateAction())->handle($feedbackId, $data);
    }

    /**
     * Delete feedback
     */
    public function delete(int $feedbackId): bool
    {
        return (new FeedbackDeleteAction())->handle($feedbackId);
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
     * Get feedback statistics
     */
    public function getStatistics(): array
    {
        $total = Feedback::count();
        $new = Feedback::where('status', 'new')->count();
        $replied = Feedback::where('status', 'replied')->count();
        $pending = Feedback::whereIn('status', ['new', 'read'])->count();

        return [
            'total' => $total,
            'new' => $new,
            'replied' => $replied,
            'pending' => $pending,
            'new_percentage' => $total > 0 ? round(($new / $total) * 100, 1) : 0,
            'replied_percentage' => $total > 0 ? round(($replied / $total) * 100, 1) : 0,
            'pending_percentage' => $total > 0 ? round(($pending / $total) * 100, 1) : 0,
        ];
    }

    /**
     * Get feedback statistics by type
     */
    public function getTypeStatistics(): array
    {
        return Feedback::selectRaw('type, count(*) as count')
            ->groupBy('type')
            ->pluck('count', 'type')
            ->toArray();
    }

    /**
     * Get feedback statistics by priority
     */
    public function getPriorityStatistics(): array
    {
        return Feedback::selectRaw('priority, count(*) as count')
            ->groupBy('priority')
            ->pluck('count', 'priority')
            ->toArray();
    }

    /**
     * Mark feedback as read
     */
    public function markAsRead(int $feedbackId): bool
    {
        $feedback = Feedback::find($feedbackId);
        if ($feedback && $feedback->status === 'new') {
            $feedback->markAsRead();
            return true;
        }
        return false;
    }

    /**
     * Mark feedback as replied
     */
    public function markAsReplied(int $feedbackId, string $response, int $repliedBy): bool
    {
        $feedback = Feedback::find($feedbackId);
        if ($feedback) {
            $feedback->markAsReplied($response, $repliedBy);
            return true;
        }
        return false;
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
            CacheHelper::clearTags(['feedbacks']);

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
}

