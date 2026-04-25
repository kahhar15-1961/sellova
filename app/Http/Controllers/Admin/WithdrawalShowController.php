<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Admin\AdminPermission;
use App\Domain\Enums\WithdrawalRequestStatus;
use App\Models\User;
use App\Models\WithdrawalRequest;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class WithdrawalShowController extends AdminPageController
{
    public function __invoke(Request $request, WithdrawalRequest $withdrawal): Response
    {
        $withdrawal->load(['seller_profile.user:id,email', 'wallet:id,wallet_type,currency,status']);

        $reviewerId = $withdrawal->getAttributes()['reviewed_by'] ?? null;
        $reviewerEmail = $reviewerId ? User::query()->whereKey($reviewerId)->value('email') : null;

        $canReview = $request->user()?->hasPermissionCode(AdminPermission::WITHDRAWALS_APPROVE) ?? false;

        return Inertia::render('Admin/Withdrawals/Show', [
            'header' => $this->pageHeader(
                'Withdrawal #'.$withdrawal->id,
                'Payout request, wallet context, and finance review actions.',
                [
                    ['label' => 'Overview', 'href' => route('admin.dashboard')],
                    ['label' => 'Withdrawals', 'href' => route('admin.withdrawals.index')],
                    ['label' => '#'.$withdrawal->id],
                ],
            ),
            'withdrawal' => [
                'id' => $withdrawal->id,
                'uuid' => $withdrawal->uuid,
                'status' => $withdrawal->status->value,
                'requested_amount' => (string) $withdrawal->requested_amount,
                'fee_amount' => (string) $withdrawal->fee_amount,
                'net_payout_amount' => (string) $withdrawal->net_payout_amount,
                'currency' => (string) ($withdrawal->currency ?? ''),
                'created_at' => $withdrawal->created_at?->toIso8601String(),
                'reviewed_at' => $withdrawal->reviewed_at?->toIso8601String(),
                'reject_reason' => $withdrawal->reject_reason,
                'seller_display' => $withdrawal->seller_profile?->display_name,
                'seller_user_email' => $withdrawal->seller_profile?->user?->email,
                'wallet' => $withdrawal->wallet === null ? null : [
                    'id' => $withdrawal->wallet->id,
                    'type' => $withdrawal->wallet->wallet_type->value,
                    'currency' => (string) ($withdrawal->wallet->currency ?? ''),
                    'status' => $withdrawal->wallet->status->value,
                ],
                'reviewer_email' => $reviewerEmail,
            ],
            'can_review' => $canReview,
            'review_open' => $canReview && $withdrawal->status === WithdrawalRequestStatus::Requested,
            'list_href' => route('admin.withdrawals.index'),
            'review_url' => route('admin.withdrawals.review', $withdrawal),
        ]);
    }
}
