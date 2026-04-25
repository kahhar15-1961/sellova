<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domain\Commands\Dispute\MoveDisputeToReviewCommand;
use App\Domain\Commands\Dispute\ResolveDisputeCommand;
use App\Domain\Enums\DisputeResolutionOutcome;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ResolveDisputeRequest;
use App\Models\DisputeCase;
use App\Models\EscrowAccount;
use App\Models\User;
use App\Services\Dispute\DisputeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

final class DisputeDispositionController extends Controller
{
    public function __construct(
        private readonly DisputeService $disputes,
    ) {}

    public function moveToReview(Request $request, DisputeCase $dispute): RedirectResponse
    {
        try {
            $this->disputes->moveToReview(new MoveDisputeToReviewCommand($dispute->id));
        } catch (\Throwable $e) {
            return redirect()
                ->route('admin.disputes.show', $dispute)
                ->withErrors(['dispute' => $e->getMessage()]);
        }

        return redirect()
            ->route('admin.disputes.show', $dispute)
            ->with('success', 'Dispute moved to review.');
    }

    public function resolve(ResolveDisputeRequest $request, DisputeCase $dispute): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $escrow = EscrowAccount::query()->where('order_id', $dispute->order_id)->first();
        if ($escrow === null) {
            return redirect()
                ->route('admin.disputes.show', $dispute)
                ->withErrors(['dispute' => 'No escrow account for this order.']);
        }

        $resolution = $request->validated('resolution');
        $outcome = $resolution === 'buyer_wins'
            ? DisputeResolutionOutcome::BuyerWins
            : DisputeResolutionOutcome::SellerWins;

        $allocateBuyer = $resolution === 'buyer_wins';
        $allocateSeller = $resolution === 'seller_wins';

        try {
            $this->disputes->resolveDispute(new ResolveDisputeCommand(
                disputeCaseId: $dispute->id,
                decidedByUserId: $user->id,
                outcome: $outcome,
                buyerAmount: '0.0000',
                sellerAmount: '0.0000',
                currency: (string) ($escrow->currency ?? 'USD'),
                reasonCode: $request->validated('reason_code'),
                notes: $request->validated('notes'),
                idempotencyKey: (string) Str::ulid(),
                allocateBuyerFullRemaining: $allocateBuyer,
                allocateSellerFullRemaining: $allocateSeller,
            ));
        } catch (\Throwable $e) {
            return redirect()
                ->route('admin.disputes.show', $dispute)
                ->withErrors(['dispute' => $e->getMessage()]);
        }

        return redirect()
            ->route('admin.disputes.show', $dispute)
            ->with('success', 'Dispute resolved.');
    }
}
