<?php

namespace App\Enums;

enum ContractTypeEnum: string
{
    case BLANKET_PURCHASE_AGREEMENT = 'blanket_purchase_agreement';
    case MASTER_SERVICE_AGREEMENT = 'master_service_agreement';
    case STATEMENT_OF_WORK = 'statement_of_work';
    case PURCHASE_ORDER = 'purchase_order';
    case FRAMEWORK_AGREEMENT = 'framework_agreement';
    case STANDARD_AGREEMENT = 'standard_agreement';
    case TASK_ORDER = 'task_order';
    case DELIVERY_ORDER = 'delivery_order';
    case SERVICE_LEVEL_AGREEMENT = 'service_level_agreement';
    case NON_DISCLOSURE_AGREEMENT = 'non_disclosure_agreement';
    case LICENSE_AGREEMENT = 'license_agreement';
    case SUBCONTRACT = 'subcontract';
    case CONSULTING_AGREEMENT = 'consulting_agreement';
    case LEASE_AGREEMENT = 'lease_agreement';
    case TERMS_AND_CONDITIONS = 'terms_and_conditions';
    case AD_HOC = 'ad_hoc';
    case NO_AGREEMENT = 'no_agreement';
    case OTHER = 'other';

    public function label(): string
    {
        return match ($this) {
            self::BLANKET_PURCHASE_AGREEMENT => 'Blanket Purchase Agreement',
            self::MASTER_SERVICE_AGREEMENT => 'Master Service Agreement',
            self::STATEMENT_OF_WORK => 'Statement of Work',
            self::PURCHASE_ORDER => 'Purchase Order',
            self::FRAMEWORK_AGREEMENT => 'Framework Agreement',
            self::STANDARD_AGREEMENT => 'Standard Agreement',
            self::TASK_ORDER => 'Task Order',
            self::DELIVERY_ORDER => 'Delivery Order',
            self::SERVICE_LEVEL_AGREEMENT => 'Service Level Agreement',
            self::NON_DISCLOSURE_AGREEMENT => 'Non-Disclosure Agreement',
            self::LICENSE_AGREEMENT => 'License Agreement',
            self::SUBCONTRACT => 'Subcontract',
            self::CONSULTING_AGREEMENT => 'Consulting Agreement',
            self::LEASE_AGREEMENT => 'Lease Agreement',
            self::TERMS_AND_CONDITIONS => 'Terms and Conditions',
            self::AD_HOC => 'Ad Hoc Agreement',
            self::NO_AGREEMENT => 'No Agreement',
            self::OTHER => 'Other',
        };
    }
}
