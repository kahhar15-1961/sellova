<?php

namespace App\Auth;

/**
 * Canonical {@see \App\Models\Role} {@code code} values used for platform authorization.
 * Keep aligned with seeded / migrated rows in {@code roles}.
 */
final class RoleCodes
{
    public const Admin = 'admin';

    public const Adjudicator = 'adjudicator';

    private function __construct()
    {
    }
}
