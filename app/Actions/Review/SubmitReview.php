<?php

namespace App\Actions\Review;

use App\Models\Assignment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SubmitReview
{
    public function handle(Assignment $assignment, User $reviewer, array $data): Assignment
    {
        return DB::transaction(function () use ($assignment, $reviewer, $data) {
            // Validate reviewer can submit review
            $this->validateReviewSubmission($assignment, $reviewer);

            // Update assignment with review data
            $assignment->update([
                'status' => 'completed',
                'completed_at' => now(),
                'notes' => $data['review_content'],
                'decline_reason' => null // Clear any previous decline reason
            ]);

            // Create review decision record
            $manuscript = $assignment->submission; // Assuming relationship exists
            $manuscript->decisions()->create([
                'user_id' => $reviewer->id,
                'type' => \App\Enums\DecisionType::REVIEW,
                'decision' => $data['recommendation'],
                'content' => $data['review_content'],
                'reasoning' => $data['detailed_comments'] ?? null,
                'recommendation' => $data['recommendation'],
                'is_final' => false,
                'completed_at' => now(),
                'metadata' => [
                    'assignment_id' => $assignment->id,
                    'review_criteria_scores' => $data['criteria_scores'] ?? [],
                    'confidential_comments' => $data['confidential_comments'] ?? null,
                    'review_round' => $assignment->round,
                    'time_to_complete' => $assignment->created_at->diffInDays(now())
                ]
            ]);

            // Update manuscript status if this was the last pending review
            $this->checkAndUpdateManuscriptStatus($manuscript);

            Log::info('Review submitted', [
                'assignment_id' => $assignment->id,
                'manuscript_id' => $manuscript->id,
                'reviewer_id' => $reviewer->id,
                'recommendation' => $data['recommendation'],
                'completed_at' => $assignment->completed_at
            ]);

            return $assignment->fresh();
        });
    }

    private function validateReviewSubmission(Assignment $assignment, User $reviewer): void
    {
        if ($assignment->assignee_id !== $reviewer->id) {
            throw new \Exception('You are not assigned to this review.');
        }

        if ($assignment->status === 'completed') {
            throw new \Exception('This review has already been completed.');
        }

        if ($assignment->status === 'declined') {
            throw new \Exception('This review assignment was declined and cannot be completed.');
        }

        if ($assignment->type !== 'review') {
            throw new \Exception('This assignment is not a review assignment.');
        }
    }

    private function checkAndUpdateManuscriptStatus($manuscript): void
    {
        // Get all pending review assignments for this manuscript
        $pendingReviews = Assignment::where('submission_id', $manuscript->id)
            ->where('type', 'review')
            ->where('status', 'pending')
            ->count();

        // If no pending reviews, update manuscript status to indicate reviews completed
        if ($pendingReviews === 0) {
            $completedReviews = Assignment::where('submission_id', $manuscript->id)
                ->where('type', 'review')
                ->where('status', 'completed')
                ->count();

            if ($completedReviews > 0) {
                // Create editorial decision noting that all reviews are complete
                $manuscript->decisions()->create([
                    'user_id' => $manuscript->journal->editor_in_chief_id,
                    'type' => \App\Enums\DecisionType::EDITORIAL,
                    'decision' => 'reviews_completed',
                    'content' => "All reviews completed. {$completedReviews} reviews received.",
                    'completed_at' => now(),
                    'metadata' => [
                        'completed_reviews_count' => $completedReviews,
                        'pending_editorial_decision' => true
                    ]
                ]);

                Log::info('All reviews completed for manuscript', [
                    'manuscript_id' => $manuscript->id,
                    'completed_reviews' => $completedReviews
                ]);
            }
        }
    }
}
