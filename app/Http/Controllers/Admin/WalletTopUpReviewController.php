<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domain\Commands\WalletTopUp\ReviewWalletTopUpCommand;
use App\Domain\Enums\WalletTopUpReviewDecision;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReviewWalletTopUpRequest;
use App\Models\User;
use App\Models\WalletTopUpRequest;
use App\Services\WalletTopUp\WalletTopUpRequestService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;

final class WalletTopUpReviewController extends Controller
{
    public function __construct(
        private readonly WalletTopUpRequestService $walletTopUps,
    ) {}

    public function store(ReviewWalletTopUpRequest $request, WalletTopUpRequest $walletTopUpRequest): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $decision = match ($request->validated('decision')) {
            'approved' => WalletTopUpReviewDecision::Approved,
            'rejected' => WalletTopUpReviewDecision::Rejected,
            default => WalletTopUpReviewDecision::Rejected,
        };

        try {
            $this->walletTopUps->reviewTopUp(new ReviewWalletTopUpCommand(
                walletTopUpRequestId: $walletTopUpRequest->id,
                reviewerUserId: $user->id,
                decision: $decision,
                reason: $request->validated('reason'),
                idempotencyKey: (string) Str::ulid(),
            ));
        } catch (\Throwable $e) {
            return redirect()
                ->route('admin.wallet-top-ups.show', $walletTopUpRequest)
                ->withErrors(['review' => $e->getMessage()]);
        }

        return redirect()
            ->route('admin.wallet-top-ups.show', $walletTopUpRequest)
            ->with('success', 'Wallet top-up request updated.');
    }
}
