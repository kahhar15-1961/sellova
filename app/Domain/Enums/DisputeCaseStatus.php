<?php

namespace App\Domain\Enums;

/**
 * Dispute case workflow states (matches `dispute_cases.status` ENUM).
 */
enum DisputeCaseStatus: string
{
    case Opened = 'opened';
    case EvidenceCollection = 'evidence_collection';
    case UnderReview = 'under_review';
    case Escalated = 'escalated';
    case Resolved = 'resolved';
}
