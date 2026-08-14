<?php

namespace App\Enums;

enum ManuscriptStatus: string
{
    case DRAFT = 'draft';
    case SUBMITTED = 'submitted';
    case UNDER_REVIEW = 'under_review';
    case REVISION_REQUESTED = 'revision_requested';
    case REVISION_SUBMITTED = 'revision_submitted';
    case ACCEPTED = 'accepted';
    case REJECTED = 'rejected';
    case WITHDRAWN = 'withdrawn';
    case IN_COPYEDIT = 'in_copyedit';
    case IN_PRODUCTION = 'in_production';
    case PUBLISHED = 'published';
    case ARCHIVED = 'archived';

    public function label(): string
    {
        return match($this) {
            self::DRAFT => 'Draft',
            self::SUBMITTED => 'Submitted',
            self::UNDER_REVIEW => 'Under Review',
            self::REVISION_REQUESTED => 'Revision Requested',
            self::REVISION_SUBMITTED => 'Revision Submitted',
            self::ACCEPTED => 'Accepted',
            self::REJECTED => 'Rejected',
            self::WITHDRAWN => 'Withdrawn',
            self::IN_COPYEDIT => 'In Copy Edit',
            self::IN_PRODUCTION => 'In Production',
            self::PUBLISHED => 'Published',
            self::ARCHIVED => 'Archived',
        };
    }

    public function canTransitionTo(self $status): bool
    {
        return match($this) {
            self::DRAFT => in_array($status, [self::SUBMITTED, self::WITHDRAWN]),
            self::SUBMITTED => in_array($status, [self::UNDER_REVIEW, self::REJECTED, self::WITHDRAWN]),
            self::UNDER_REVIEW => in_array($status, [self::REVISION_REQUESTED, self::ACCEPTED, self::REJECTED]),
            self::REVISION_REQUESTED => in_array($status, [self::REVISION_SUBMITTED, self::WITHDRAWN]),
            self::REVISION_SUBMITTED => in_array($status, [self::UNDER_REVIEW, self::ACCEPTED, self::REJECTED]),
            self::ACCEPTED => in_array($status, [self::IN_COPYEDIT, self::PUBLISHED]),
            self::IN_COPYEDIT => in_array($status, [self::IN_PRODUCTION, self::ACCEPTED]),
            self::IN_PRODUCTION => in_array($status, [self::PUBLISHED, self::IN_COPYEDIT]),
            default => false,
        };
    }
}
