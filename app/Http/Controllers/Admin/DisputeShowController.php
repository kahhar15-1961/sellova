<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Admin\AdminPermission;
use App\Domain\Enums\DisputeCaseStatus;
use App\Domain\Enums\EscrowState;
use App\Models\DisputeCase;
use App\Models\EscrowAccount;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class DisputeShowController extends AdminPageController
{
    public function __invoke(Request $request, DisputeCase $dispute): Response
    {
        $dispute->load([
            'order:id,order_number,buyer_user_id,currency',
            'order.buyer:id,email',
            'opened_by_user:id,email',
            'disputeEvidences' => static fn ($q) => $q->orderByDesc('id')->limit(50),
            'disputeDecision',
        ]);

        $escrow = EscrowAccount::query()->where('order_id', $dispute->order_id)->first();

        $canResolve = $request->user()?->hasPermissionCode(AdminPermission::DISPUTES_RESOLVE) ?? false;

        $evidence = $dispute->disputeEvidences->map(static fn ($e): array => [
            'id' => $e->id,
            'type' => $e->evidence_type,
            'submitted_at' => $e->submitted_at?->toIso8601String(),
            'preview' => $e->content_text !== null && $e->content_text !== ''
                ? mb_substr((string) $e->content_text, 0, 280)
                : ($e->storage_path ?? '—'),
        ]);

        $decision = $dispute->disputeDecision;

        $canMoveToReview = $canResolve && in_array($dispute->status, [
            DisputeCaseStatus::Opened,
            DisputeCaseStatus::EvidenceCollection,
        ], true);

        $canResolveHere = $canResolve
            && in_array($dispute->status, [DisputeCaseStatus::UnderReview, DisputeCaseStatus::Escalated], true)
            && $escrow !== null
            && $escrow->state === EscrowState::UnderDispute;

        return Inertia::render('Admin/Disputes/Show', [
            'header' => $this->pageHeader(
                'Dispute #'.$dispute->id,
                'Case file, evidence, escrow linkage, and adjudication actions.',
                [
                    ['label' => 'Overview', 'href' => route('admin.dashboard')],
                    ['label' => 'Disputes', 'href' => route('admin.disputes.index')],
                    ['label' => '#'.$dispute->id],
                ],
            ),
            'dispute' => [
                'id' => $dispute->id,
                'uuid' => $dispute->uuid,
                'status' => $dispute->status->value,
                'opened_at' => $dispute->opened_at?->toIso8601String(),
                'resolution_outcome' => $dispute->resolution_outcome?->value,
                'order_number' => $dispute->order?->order_number,
                'buyer_email' => $dispute->order?->buyer?->email,
                'opened_by_email' => $dispute->opened_by_user?->email,
            ],
            'escrow' => $escrow === null ? null : [
                'id' => $escrow->id,
                'state' => $escrow->state->value,
                'held_amount' => (string) $escrow->held_amount,
                'currency' => (string) ($escrow->currency ?? ''),
            ],
            'evidence' => $evidence,
            'decision' => $decision === null ? null : [
                'outcome' => $decision->outcome->value,
                'buyer_amount' => (string) $decision->buyer_amount,
                'seller_amount' => (string) $decision->seller_amount,
                'currency' => (string) $decision->currency,
                'reason_code' => (string) $decision->reason_code,
                'decided_at' => $decision->decided_at?->toIso8601String(),
            ],
            'can_move_to_review' => $canMoveToReview,
            'can_resolve' => $canResolveHere,
            'list_href' => route('admin.disputes.index'),
            'move_to_review_url' => route('admin.disputes.move-to-review', $dispute),
            'resolve_url' => route('admin.disputes.resolve', $dispute),
        ]);
    }
}
