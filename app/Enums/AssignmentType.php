<?php

namespace App\Enums;

enum AssignmentType: string
{
    case REVIEW = 'review';
    case EDITORIAL = 'editorial';
    case TECHNICAL_CHECK = 'technical_check';
    case LANGUAGE_EDITING = 'language_editing';
    case FINAL_REVIEW = 'final_review';
    case COPY_EDITING = 'copy_editing';
    case PROOFREADING = 'proofreading';

    public function label(): string
    {
        return match($this) {
            self::REVIEW => 'Peer Review',
            self::EDITORIAL => 'Editorial Review',
            self::TECHNICAL_CHECK => 'Technical Check',
            self::LANGUAGE_EDITING => 'Language Editing',
            self::FINAL_REVIEW => 'Final Review',
            self::COPY_EDITING => 'Copy Editing',
            self::PROOFREADING => 'Proofreading',
        };
    }

    public function description(): string
    {
        return match($this) {
            self::REVIEW => 'Peer review assignment to subject matter experts',
            self::EDITORIAL => 'Editorial board review and decision making',
            self::TECHNICAL_CHECK => 'Technical formatting and compliance check',
            self::LANGUAGE_EDITING => 'Language and grammar review',
            self::FINAL_REVIEW => 'Final review before publication',
            self::COPY_EDITING => 'Copy editing for style and consistency',
            self::PROOFREADING => 'Final proofreading before publication',
        };
    }
}
