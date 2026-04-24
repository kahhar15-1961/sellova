<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Admin\AdminPermission;
use App\Domain\Commands\UserSeller\ClaimKycForReviewCommand;
use App\Domain\Commands\UserSeller\ReviewKycCommand;
use App\Domain\Exceptions\AuthValidationFailedException;
use App\Domain\Exceptions\InvalidDomainStateTransitionException;
use App\Http\Requests\Admin\ClaimSellerKycRequest;
use App\Http\Requests\Admin\ReviewSellerKycRequest;
use App\Models\KycDocument;
use App\Models\KycVerification;
use App\Models\User;
use App\Services\Admin\SellerVerificationReadService;
use App\Services\UserSeller\UserSellerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

final class SellerVerificationController extends AdminPageController
{
    public function __construct(
        private readonly SellerVerificationReadService $read,
        private readonly UserSellerService $userSeller,
    ) {}

    public function index(Request $request): Response
    {
        $queue = $this->read->paginatedQueue($request);

        return Inertia::render('Admin/Sellers/Index', [
            'header' => $this->pageHeader(
                'Sellers & KYC',
                'Enterprise verification queue with full audit trail and controlled document access.',
                [
                    ['label' => 'Overview', 'href' => route('admin.dashboard')],
                    ['label' => 'Marketplace'],
                    ['label' => 'Sellers & KYC'],
                ],
            ),
            'tab' => (string) $request->query('tab', 'pending'),
            'q' => (string) $request->query('q', ''),
            'rows' => $queue['rows'],
            'pagination' => $queue['pagination'],
        ]);
    }

    public function show(Request $request, KycVerification $kyc): Response
    {
        /** @var User $user */
        $user = $request->user();

        return Inertia::render('Admin/Sellers/VerificationWorkspace', [
            'header' => $this->pageHeader(
                'KYC verification',
                'Review identity evidence, claim the case, and record a defensible decision.',
                [
                    ['label' => 'Overview', 'href' => route('admin.dashboard')],
                    ['label' => 'Sellers & KYC', 'href' => route('admin.sellers.index')],
                    ['label' => 'Case #'.$kyc->id],
                ],
            ),
            'workspace' => $this->read->workspace($kyc, $user),
        ]);
    }

    public function claim(ClaimSellerKycRequest $request, KycVerification $kyc): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        try {
            $this->userSeller->claimKycForReview(new ClaimKycForReviewCommand(
                kycVerificationId: (int) $kyc->id,
                reviewerId: (int) $user->id,
                correlationId: $this->correlationId($request),
                ipAddress: $request->ip(),
                userAgent: $request->userAgent(),
            ));
        } catch (InvalidDomainStateTransitionException $e) {
            return back()->withErrors(['claim' => $e->getMessage()])->with('error', 'Unable to claim this case.');
        } catch (AuthValidationFailedException $e) {
            return back()->withErrors(['claim' => $e->getMessage()])->with('error', 'Case not found.');
        }

        return back()->with('success', 'Case locked for review.');
    }

    public function review(ReviewSellerKycRequest $request, KycVerification $kyc): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $validated = $request->validated();

        try {
            $this->userSeller->reviewKyc(new ReviewKycCommand(
                kycVerificationId: (int) $kyc->id,
                reviewerId: (int) $user->id,
                decision: (string) $validated['decision'],
                reason: isset($validated['reason']) ? (string) $validated['reason'] : null,
                correlationId: $this->correlationId($request),
                ipAddress: $request->ip(),
                userAgent: $request->userAgent(),
            ));
        } catch (InvalidDomainStateTransitionException $e) {
            return back()->withErrors(['review' => $e->getMessage()])->with('error', 'Invalid review transition.');
        } catch (AuthValidationFailedException $e) {
            return back()->withErrors(['review' => $e->getMessage()])->with('error', 'Unable to complete review.');
        }

        return redirect()
            ->route('admin.sellers.kyc.show', ['kyc' => $kyc->id])
            ->with('success', 'Verification decision recorded.');
    }

    public function downloadDocument(Request $request, KycDocument $document): BinaryFileResponse|SymfonyResponse
    {
        $user = $request->user();
        if ($user === null || ! $user->hasPermissionCode(AdminPermission::SELLERS_VIEW)) {
            abort(SymfonyResponse::HTTP_FORBIDDEN);
        }

        $path = $this->normalizeStoragePath($document->storage_path);
        if ($path === null || str_contains($path, '..')) {
            abort(SymfonyResponse::HTTP_NOT_FOUND);
        }

        $disk = Storage::disk('local');
        if (! $disk->exists($path)) {
            abort(SymfonyResponse::HTTP_NOT_FOUND, 'Document file is not available on this environment.');
        }

        $absolute = $disk->path($path);
        if (! is_readable($absolute)) {
            abort(SymfonyResponse::HTTP_NOT_FOUND);
        }

        return response()->download($absolute, basename($path), [
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    private function correlationId(Request $request): string
    {
        $header = $request->headers->get('X-Correlation-Id');
        if (is_string($header) && preg_match('/^[a-zA-Z0-9\-]{8,128}$/', $header)) {
            return $header;
        }

        return (string) Str::uuid();
    }

    private function normalizeStoragePath(?string $storagePath): ?string
    {
        if ($storagePath === null || $storagePath === '') {
            return null;
        }

        return ltrim(str_replace('\\', '/', $storagePath), '/');
    }
}
