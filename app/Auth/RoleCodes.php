<?php

namespace App\Auth;

/**
 * Canonical {@see \App\Models\Role} {@code code} values used for platform authorization.
 * Keep aligned with seeded / migrated rows in {@code roles}.
 */
final class RoleCodes
{
    public const SuperAdmin = 'super_admin';

    public const Admin = 'admin';

    public const FinanceAdmin = 'finance_admin';

    public const DisputeOfficer = 'dispute_officer';

    public const KycReviewer = 'kyc_reviewer';

    public const SupportAgent = 'support_agent';

    /** Legacy / domain adjudication role (may overlap with {@see self::DisputeOfficer}). */
    public const Adjudicator = 'adjudicator';

    private function __construct()
    {
    }
}
