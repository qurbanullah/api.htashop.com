<?php

namespace App\Enums;

enum ApprovalStatus: string
{
    // Initial state
    case PENDING_INITIAL_REVIEW = 'pending_initial_review'; // Manuscript submitted, waiting for editor

    // Editor screening
    case EDITOR_SCREENING = 'editor_screening'; // Editor is checking manuscript for basic requirements
    case DESK_REJECTED = 'desk_rejected'; // Rejected without review (desk rejection)

    // Reviewer assignment phase
    case APPROVED_FOR_REVIEW = 'approved_for_review'; // Editor approved, reviewers will be assigned
    case REVIEWERS_ASSIGNED = 'reviewers_assigned'; // Reviewers assigned, waiting for acceptance
    case REVIEW_IN_PROGRESS = 'review_in_progress'; // At least one reviewer accepted

    // Review completed
    case REVIEWS_COMPLETED = 'reviews_completed'; // All reviews submitted
    case EDITOR_DECISION_PENDING = 'editor_decision_pending'; // Editor reviewing recommendations

    // Post-review decisions
    case ACCEPTED_FOR_PUBLICATION = 'accepted_for_publication'; // Final acceptance
    case MINOR_REVISION_REQUIRED = 'minor_revision_required';
    case MAJOR_REVISION_REQUIRED = 'major_revision_required';
    case REJECTED_AFTER_REVIEW = 'rejected_after_review';

    // Revision cycle
    case REVISION_UNDER_REVIEW = 'revision_under_review'; // Revised manuscript being reviewed

    // Final states
    case APPROVED_FOR_COPYEDIT = 'approved_for_copyedit'; // Ready for copy editing
    case APPROVED_FOR_PRODUCTION = 'approved_for_production'; // Ready for production
    case APPROVED_FOR_PUBLISHING = 'approved_for_publishing'; // Final approval to publish

    public function label(): string
    {
        return match($this) {
            self::PENDING_INITIAL_REVIEW => 'Pending Initial Review',
            self::EDITOR_SCREENING => 'Editor Screening',
            self::DESK_REJECTED => 'Desk Rejected',
            self::APPROVED_FOR_REVIEW => 'Approved for Review',
            self::REVIEWERS_ASSIGNED => 'Reviewers Assigned',
            self::REVIEW_IN_PROGRESS => 'Review in Progress',
            self::REVIEWS_COMPLETED => 'Reviews Completed',
            self::EDITOR_DECISION_PENDING => 'Editor Decision Pending',
            self::ACCEPTED_FOR_PUBLICATION => 'Accepted for Publication',
            self::MINOR_REVISION_REQUIRED => 'Minor Revision Required',
            self::MAJOR_REVISION_REQUIRED => 'Major Revision Required',
            self::REJECTED_AFTER_REVIEW => 'Rejected After Review',
            self::REVISION_UNDER_REVIEW => 'Revision Under Review',
            self::APPROVED_FOR_COPYEDIT => 'Approved for Copy Edit',
            self::APPROVED_FOR_PRODUCTION => 'Approved for Production',
            self::APPROVED_FOR_PUBLISHING => 'Approved for Publishing',
        };
    }

    /**
     * Get the corresponding ManuscriptStatus for this approval status
     */
    public function toManuscriptStatus(): ManuscriptStatus
    {
        return match($this) {
            self::PENDING_INITIAL_REVIEW,
            self::EDITOR_SCREENING => ManuscriptStatus::SUBMITTED,

            self::APPROVED_FOR_REVIEW,
            self::REVIEWERS_ASSIGNED,
            self::REVIEW_IN_PROGRESS,
            self::REVIEWS_COMPLETED,
            self::EDITOR_DECISION_PENDING,
            self::REVISION_UNDER_REVIEW => ManuscriptStatus::UNDER_REVIEW,

            self::DESK_REJECTED,
            self::REJECTED_AFTER_REVIEW => ManuscriptStatus::REJECTED,

            self::MINOR_REVISION_REQUIRED,
            self::MAJOR_REVISION_REQUIRED => ManuscriptStatus::REVISION_REQUESTED,

            self::ACCEPTED_FOR_PUBLICATION => ManuscriptStatus::ACCEPTED,
            self::APPROVED_FOR_COPYEDIT => ManuscriptStatus::IN_COPYEDIT,
            self::APPROVED_FOR_PRODUCTION => ManuscriptStatus::IN_PRODUCTION,
            self::APPROVED_FOR_PUBLISHING => ManuscriptStatus::PUBLISHED,
        };
    }

    /**
     * Check if this status should trigger reviewer assignment
     */
    public function shouldAssignReviewers(): bool
    {
        return $this === self::APPROVED_FOR_REVIEW;
    }

    /**
     * Check if this status should trigger email notifications
     */
    public function shouldNotifyReviewers(): bool
    {
        return in_array($this, [
            self::APPROVED_FOR_REVIEW,
            self::REVIEWERS_ASSIGNED,
        ]);
    }

    /**
     * Check if this status should notify author
     */
    public function shouldNotifyAuthor(): bool
    {
        return in_array($this, [
            self::APPROVED_FOR_REVIEW,
            self::DESK_REJECTED,
            self::ACCEPTED_FOR_PUBLICATION,
            self::MINOR_REVISION_REQUIRED,
            self::MAJOR_REVISION_REQUIRED,
            self::REJECTED_AFTER_REVIEW,
            self::APPROVED_FOR_PUBLISHING,
        ]);
    }

    /**
     * Valid transitions from current status
     */
    public function canTransitionTo(self $status): bool
    {
        return match($this) {
            self::PENDING_INITIAL_REVIEW => in_array($status, [
                self::EDITOR_SCREENING,
                self::DESK_REJECTED,
            ]),

            self::EDITOR_SCREENING => in_array($status, [
                self::APPROVED_FOR_REVIEW,
                self::DESK_REJECTED,
            ]),

            self::APPROVED_FOR_REVIEW => in_array($status, [
                self::REVIEWERS_ASSIGNED,
            ]),

            self::REVIEWERS_ASSIGNED => in_array($status, [
                self::REVIEW_IN_PROGRESS,
                self::APPROVED_FOR_REVIEW, // Re-assign different reviewers
            ]),

            self::REVIEW_IN_PROGRESS => in_array($status, [
                self::REVIEWS_COMPLETED,
            ]),

            self::REVIEWS_COMPLETED => in_array($status, [
                self::EDITOR_DECISION_PENDING,
            ]),

            self::EDITOR_DECISION_PENDING => in_array($status, [
                self::ACCEPTED_FOR_PUBLICATION,
                self::MINOR_REVISION_REQUIRED,
                self::MAJOR_REVISION_REQUIRED,
                self::REJECTED_AFTER_REVIEW,
            ]),

            self::MINOR_REVISION_REQUIRED,
            self::MAJOR_REVISION_REQUIRED => in_array($status, [
                self::REVISION_UNDER_REVIEW,
            ]),

            self::REVISION_UNDER_REVIEW => in_array($status, [
                self::REVIEWS_COMPLETED,
                self::ACCEPTED_FOR_PUBLICATION,
            ]),

            self::ACCEPTED_FOR_PUBLICATION => in_array($status, [
                self::APPROVED_FOR_COPYEDIT,
            ]),

            self::APPROVED_FOR_COPYEDIT => in_array($status, [
                self::APPROVED_FOR_PRODUCTION,
            ]),

            self::APPROVED_FOR_PRODUCTION => in_array($status, [
                self::APPROVED_FOR_PUBLISHING,
            ]),

            default => false,
        };
    }
}
