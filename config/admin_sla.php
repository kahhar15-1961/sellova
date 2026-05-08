<?php

declare(strict_types=1);

return [
    'kyc' => [
        'warning_hours' => (int) env('ADMIN_SLA_KYC_WARNING_HOURS', 12),
        'breach_hours' => (int) env('ADMIN_SLA_KYC_BREACH_HOURS', 24),
    ],
    'disputes' => [
        'warning_hours' => (int) env('ADMIN_SLA_DISPUTE_WARNING_HOURS', 24),
        'breach_hours' => (int) env('ADMIN_SLA_DISPUTE_BREACH_HOURS', 48),
    ],
    'withdrawals' => [
        'warning_hours' => (int) env('ADMIN_SLA_WITHDRAWAL_WARNING_HOURS', 12),
        'breach_hours' => (int) env('ADMIN_SLA_WITHDRAWAL_BREACH_HOURS', 24),
    ],
];
