<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domain\Commands\UserSeller\BulkClaimKycForReviewCommand;
use App\Domain\Commands\UserSeller\ReassignKycForReviewCommand;
use App\Domain\Exceptions\AuthValidationFailedException;
use App\Domain\Exceptions\InvalidDomainStateTransitionException;
use App\Http\Requests\Admin\BulkClaimSellerKycRequest;
use App\Http\Requests\Admin\ReassignSellerKycRequest;
use App\Models\KycVerification;
use App\Models\User;
use App\Services\UserSeller\UserSellerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class SellerVerificationAssignmentController extends AdminPageController
{
    public function __construct(
        private readonly UserSellerService $userSeller,
    ) {}

    public function reassign(ReassignSellerKycRequest $request, KycVerification $kyc): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $validated = $request->validated();

        try {
            $this->userSeller->reassignKycForReview(new ReassignKycForReviewCommand(
                kycVerificationId: (int) $kyc->id,
                actorId: (int) $actor->id,
                assigneeId: (int) $validated['assignee_user_id'],
                correlationId: $this->correlationId($request),
                ipAddress: $request->ip(),
                userAgent: $request->userAgent(),
            ));
        } catch (InvalidDomainStateTransitionException|AuthValidationFailedException $e) {
            return back()->withErrors(['reassign' => $e->getMessage()])->with('error', 'Unable to reassign this case.');
        }

        return back()->with('success', 'KYC reassigned.');
    }

    public function bulkClaim(BulkClaimSellerKycRequest $request): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $validated = $request->validated();

        try {
            $result = $this->userSeller->bulkClaimKycForReview(new BulkClaimKycForReviewCommand(
                kycVerificationIds: array_map('intval', $validated['kyc_ids']),
                reviewerId: (int) $actor->id,
                correlationId: $this->correlationId($request),
                ipAddress: $request->ip(),
                userAgent: $request->userAgent(),
            ));
        } catch (InvalidDomainStateTransitionException|AuthValidationFailedException $e) {
            return back()->withErrors(['bulk_claim' => $e->getMessage()])->with('error', 'Unable to claim selected cases.');
        }

        $claimed = (int) ($result['claimed_count'] ?? 0);
        $skipped = (int) ($result['skipped_count'] ?? 0);
        $message = $skipped > 0
            ? "{$claimed} case(s) claimed; {$skipped} skipped."
            : "{$claimed} case(s) claimed.";

        return back()->with('success', $message);
    }

    private function correlationId(Request $request): string
    {
        $header = $request->headers->get('X-Correlation-Id');
        if (is_string($header) && preg_match('/^[a-zA-Z0-9\-]{8,128}$/', $header)) {
            return $header;
        }

        return (string) \Illuminate\Support\Str::uuid();
    }
}
