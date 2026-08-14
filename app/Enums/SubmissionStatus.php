<?php

namespace App\Enums;

enum SubmissionStatus: string
{
    case DRAFT = 'draft';
    case SUBMITTED = 'submitted';
    case UNDER_REVIEW = 'under_review';
    case REVISION_REQUESTED = 'revision_requested';
    case REVISED = 'revised';
    case ACCEPTED = 'accepted';
    case REJECTED = 'rejected';
    case WITHDRAWN = 'withdrawn';
    case PUBLISHED = 'published';
    case ARCHIVED = 'archived';

    public function label(): string
    {
        return match($this) {
            self::DRAFT => 'Draft',
            self::SUBMITTED => 'Submitted',
            self::UNDER_REVIEW => 'Under Review',
            self::REVISION_REQUESTED => 'Revision Requested',
            self::REVISED => 'Revised',
            self::ACCEPTED => 'Accepted',
            self::REJECTED => 'Rejected',
            self::WITHDRAWN => 'Withdrawn',
            self::PUBLISHED => 'Published',
            self::ARCHIVED => 'Archived',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::DRAFT => 'gray',
            self::SUBMITTED => 'blue',
            self::UNDER_REVIEW => 'yellow',
            self::REVISION_REQUESTED => 'orange',
            self::REVISED => 'purple',
            self::ACCEPTED => 'green',
            self::REJECTED => 'red',
            self::WITHDRAWN => 'gray',
            self::PUBLISHED => 'emerald',
            self::ARCHIVED => 'slate',
        };
    }
}
