<?php

namespace App\Enums;

enum DecisionType: string
{
    case EDITORIAL = 'editorial';
    case REVIEW = 'review';
    case COPYEDIT = 'copyedit';
    case PROOFREADING = 'proofreading';
    case PRODUCTION = 'production';
    case PUBLICATION = 'publication';
    case REJECTION = 'rejection';
    case REVISION_REQUEST = 'revision_request';
    case FINAL_ACCEPTANCE = 'final_acceptance';

    public function label(): string
    {
        return match($this) {
            self::EDITORIAL => 'Editorial Decision',
            self::REVIEW => 'Review Decision',
            self::COPYEDIT => 'Copy Edit Decision',
            self::PROOFREADING => 'Proofreading Decision',
            self::PRODUCTION => 'Production Decision',
            self::PUBLICATION => 'Publication Decision',
            self::REJECTION => 'Rejection Decision',
            self::REVISION_REQUEST => 'Revision Request',
            self::FINAL_ACCEPTANCE => 'Final Acceptance',
        };
    }

    public function description(): string
    {
        return match($this) {
            self::EDITORIAL => 'Decision made by editor regarding manuscript progression',
            self::REVIEW => 'Decision made by peer reviewer',
            self::COPYEDIT => 'Decision regarding copy editing requirements',
            self::PROOFREADING => 'Decision regarding proofreading requirements',
            self::PRODUCTION => 'Decision regarding production requirements',
            self::PUBLICATION => 'Decision to publish or not',
            self::REJECTION => 'Decision to reject manuscript',
            self::REVISION_REQUEST => 'Request for manuscript revision',
            self::FINAL_ACCEPTANCE => 'Final acceptance for publication',
        };
    }
}
