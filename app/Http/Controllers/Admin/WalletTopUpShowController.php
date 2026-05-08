<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Admin\AdminPermission;
use App\Domain\Enums\WalletTopUpRequestStatus;
use App\Models\WalletTopUpRequest;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class WalletTopUpShowController extends AdminPageController
{
    public function __invoke(Request $request, WalletTopUpRequest $walletTopUpRequest): Response
    {
        $walletTopUpRequest->load([
            'wallet.user:id,email',
            'requested_by_user:id,email',
            'reviewed_by_user:id,email',
        ]);

        $canReview = $request->user()?->hasPermissionCode(AdminPermission::WALLETS_MANAGE) ?? false;
        $reviewerEmail = $walletTopUpRequest->reviewed_by_user?->email;

        return Inertia::render('Admin/WalletTopUps/Show', [
            'header' => $this->pageHeader(
                'Top-up #'.$walletTopUpRequest->id,
                'Funding request, wallet context, and approval action.',
                [
                    ['label' => 'Overview', 'href' => route('admin.dashboard')],
                    ['label' => 'Wallet top-ups', 'href' => route('admin.wallet-top-ups.index')],
                    ['label' => '#'.$walletTopUpRequest->id],
                ],
            ),
            'request' => [
                'id' => $walletTopUpRequest->id,
                'uuid' => $walletTopUpRequest->uuid,
                'status' => $walletTopUpRequest->status->value,
                'requested_amount' => (string) $walletTopUpRequest->requested_amount,
                'payment_method' => (string) ($walletTopUpRequest->payment_method ?? ''),
                'payment_reference' => (string) ($walletTopUpRequest->payment_reference ?? ''),
                'currency' => (string) ($walletTopUpRequest->currency ?? ''),
                'created_at' => $walletTopUpRequest->created_at?->toIso8601String(),
                'reviewed_at' => $walletTopUpRequest->reviewed_at?->toIso8601String(),
                'rejection_reason' => $walletTopUpRequest->rejection_reason,
                'requested_by_email' => $walletTopUpRequest->requested_by_user?->email,
                'reviewer_email' => $reviewerEmail,
                'wallet' => $walletTopUpRequest->wallet === null ? null : [
                    'id' => $walletTopUpRequest->wallet->id,
                    'type' => $walletTopUpRequest->wallet->wallet_type->value,
                    'currency' => (string) $walletTopUpRequest->wallet->currency,
                    'status' => $walletTopUpRequest->wallet->status->value,
                    'user_email' => $walletTopUpRequest->wallet->user?->email,
                ],
            ],
            'can_review' => $canReview,
            'review_open' => $canReview && $walletTopUpRequest->status === WalletTopUpRequestStatus::Requested,
            'list_href' => route('admin.wallet-top-ups.index'),
            'review_url' => route('admin.wallet-top-ups.review', $walletTopUpRequest),
        ]);
    }
}
