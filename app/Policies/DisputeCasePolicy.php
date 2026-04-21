<?php

namespace App\Policies;

use App\Auth\OrderParticipant;
use App\Domain\Enums\DisputeCaseStatus;
use App\Models\DisputeCase;
use App\Models\Order;
use App\Models\User;

final class DisputeCasePolicy
{
    public function view(User $actor, DisputeCase $case): bool
    {
        if ($actor->isPlatformStaff()) {
            return true;
        }

        $case->loadMissing('order');
        $order = $case->order;
        if ($order === null) {
            return false;
        }

        if (OrderParticipant::isParticipant($actor, $order)) {
            return true;
        }

        return (int) $case->opened_by_user_id === (int) $actor->id;
    }

    /**
     * Buyers and sellers on the order may submit evidence while the case is collecting; staff may assist.
     */
    public function submitEvidence(User $actor, DisputeCase $case): bool
    {
        if (! in_array($case->status, [DisputeCaseStatus::Opened, DisputeCaseStatus::EvidenceCollection], true)) {
            return false;
        }

        if ($actor->isPlatformStaff()) {
            return true;
        }

        $case->loadMissing('order');
        $order = $case->order;
        if ($order === null) {
            return false;
        }

        return OrderParticipant::isParticipant($actor, $order);
    }

    /**
     * Move to review: participants (buyer/seller) or staff while pre-resolution.
     */
    public function moveToReview(User $actor, DisputeCase $case): bool
    {
        if (! in_array($case->status, [DisputeCaseStatus::Opened, DisputeCaseStatus::EvidenceCollection], true)) {
            return false;
        }

        if ($actor->isPlatformStaff()) {
            return true;
        }

        $case->loadMissing('order');
        $order = $case->order;
        if ($order === null) {
            return false;
        }

        return OrderParticipant::isParticipant($actor, $order);
    }

    /**
     * Escalation while under review: participants or staff (not a seller-only adjudication path).
     */
    public function escalate(User $actor, DisputeCase $case): bool
    {
        if ($case->status !== DisputeCaseStatus::UnderReview) {
            return false;
        }

        if ($actor->isPlatformStaff()) {
            return true;
        }

        $case->loadMissing('order');
        $order = $case->order;
        if ($order === null) {
            return false;
        }

        return OrderParticipant::isParticipant($actor, $order);
    }

    /**
     * Adjudication / monetary resolution: **only** platform staff (admin or adjudicator).
     * Sellers must never resolve disputes through this ability, even if they are also buyers elsewhere.
     */
    public function resolve(User $actor, DisputeCase $case): bool
    {
        if (! in_array($case->status, [DisputeCaseStatus::UnderReview, DisputeCaseStatus::Escalated], true)) {
            return false;
        }

        return $actor->isPlatformStaff();
    }
}
