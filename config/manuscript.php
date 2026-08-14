<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Number of Reviewers
    |--------------------------------------------------------------------------
    |
    | The default number of reviewers to assign to a manuscript when using
    | the auto-assignment feature.
    |
    */
    'default_reviewers_count' => env('MANUSCRIPT_DEFAULT_REVIEWERS', 2),

    /*
    |--------------------------------------------------------------------------
    | Auto-Assignment Behavior
    |--------------------------------------------------------------------------
    |
    | Determines when reviewers are automatically assigned:
    | - 'on_submission': Immediately when author submits manuscript
    | - 'on_approval': When editor approves manuscript for review
    | - 'hybrid': Try on submission, fallback on approval
    | - 'manual_only': Never auto-assign, editor must manually assign
    |
    */
    'auto_assignment_mode' => env('MANUSCRIPT_AUTO_ASSIGNMENT_MODE', 'hybrid'),

    /*
    |--------------------------------------------------------------------------
    | Review Due Date (Days)
    |--------------------------------------------------------------------------
    |
    | Default number of days from assignment for reviewers to complete review.
    |
    */
    'review_due_days' => env('MANUSCRIPT_REVIEW_DUE_DAYS', 14),

    /*
    |--------------------------------------------------------------------------
    | Email Notifications
    |--------------------------------------------------------------------------
    |
    | Enable/disable email notifications for various manuscript events.
    |
    */
    'notifications' => [
        'reviewer_assignment' => env('NOTIFY_REVIEWER_ASSIGNMENT', true),
        'author_on_approval' => env('NOTIFY_AUTHOR_APPROVAL', true),
        'author_on_rejection' => env('NOTIFY_AUTHOR_REJECTION', true),
        'reminder_before_due' => env('NOTIFY_REMINDER_DAYS', 2), // Days before due date
    ],

    /*
    |--------------------------------------------------------------------------
    | Approval Workflow
    |--------------------------------------------------------------------------
    |
    | Settings for manuscript approval workflow.
    |
    */
    'workflow' => [
        'require_editor_approval' => env('MANUSCRIPT_REQUIRE_EDITOR_APPROVAL', true),
        'allow_desk_rejection' => env('MANUSCRIPT_ALLOW_DESK_REJECTION', true),
    ],
];
