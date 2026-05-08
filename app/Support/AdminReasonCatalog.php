<?php

declare(strict_types=1);

namespace App\Support;

final class AdminReasonCatalog
{
    /** @return list<string> */
    public static function buyerRiskCodes(): array
    {
        return ['fraud_signal_velocity', 'chargeback_pattern', 'account_takeover_risk', 'compliance_review', 'manual_risk_escalation'];
    }

    /** @return list<string> */
    public static function sellerStoreCodes(): array
    {
        return ['policy_breach', 'counterfeit_risk', 'kyc_expired', 'manual_quality_hold', 'legal_compliance_hold'];
    }

    /** @return list<string> */
    public static function productPolicyCodes(): array
    {
        return ['policy_violation', 'counterfeit_risk', 'misleading_listing', 'insufficient_metadata', 'manual_quality_hold'];
    }

    /** @return list<string> */
    public static function escrowActionCodes(): array
    {
        return ['buyer_protection', 'seller_release_confirmation', 'refund_on_request', 'fraud_risk_hold', 'dispute_resolution', 'manual_settlement'];
    }

    private function __construct() {}
}
