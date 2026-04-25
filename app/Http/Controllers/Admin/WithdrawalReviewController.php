<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domain\Commands\Withdrawal\ReviewWithdrawalCommand;
use App\Domain\Enums\WithdrawalReviewDecision;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReviewWithdrawalRequest;
use App\Models\User;
use App\Models\WithdrawalRequest;
use App\Services\Withdrawal\WithdrawalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;

final class WithdrawalReviewController extends Controller
{
    public function __construct(
        private readonly WithdrawalService $withdrawals,
    ) {}

    public function store(ReviewWithdrawalRequest $request, WithdrawalRequest $withdrawal): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $decision = match ($request->validated('decision')) {
            'approved' => WithdrawalReviewDecision::Approved,
            'rejected' => WithdrawalReviewDecision::Rejected,
            default => WithdrawalReviewDecision::Rejected,
        };

        try {
            $this->withdrawals->reviewWithdrawal(new ReviewWithdrawalCommand(
                withdrawalRequestId: $withdrawal->id,
                reviewerId: $user->id,
                decision: $decision,
                reason: $request->validated('reason'),
                idempotencyKey: (string) Str::ulid(),
            ));
        } catch (\Throwable $e) {
            return redirect()
                ->route('admin.withdrawals.show', $withdrawal)
                ->withErrors(['review' => $e->getMessage()]);
        }

        return redirect()
            ->route('admin.withdrawals.show', $withdrawal)
            ->with('success', 'Withdrawal request updated.');
    }
}
