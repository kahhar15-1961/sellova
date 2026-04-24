<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Admin\AdminPermission;
use App\Models\AuditLog;
use App\Models\KycDocument;
use App\Models\KycVerification;
use App\Models\User;
use App\Services\Audit\AuditLogWriter;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Read models and presentation DTOs for the seller / KYC verification workspace.
 */
final class SellerVerificationReadService
{
    /**
     * @return array{rows: list<array<string, mixed>>, pagination: array<string, int|string|null>}
     */
    public function paginatedQueue(Request $request): array
    {
        $tabRaw = (string) $request->query('tab', 'pending');
        $tab = in_array($tabRaw, ['pending', 'all', 'approved', 'rejected', 'expired'], true) ? $tabRaw : 'pending';
        $search = trim((string) $request->query('q', ''));

        $query = KycVerification::query()
            ->select([
                'kyc_verifications.id',
                'kyc_verifications.uuid',
                'kyc_verifications.seller_profile_id',
                'kyc_verifications.status',
                'kyc_verifications.submitted_at',
                'kyc_verifications.reviewed_at',
            ])
            ->with([
                'seller_profile' => static function ($q): void {
                    $q->select(['id', 'user_id', 'display_name', 'verification_status', 'country_code', 'default_currency']);
                },
                'seller_profile.user' => static function ($q): void {
                    $q->select(['id', 'email', 'uuid']);
                },
            ])
            ->orderByDesc('kyc_verifications.submitted_at');

        if ($tab === 'pending') {
            $query->whereIn('kyc_verifications.status', ['submitted', 'under_review']);
        } elseif ($tab === 'approved') {
            $query->where('kyc_verifications.status', 'approved');
        } elseif ($tab === 'rejected') {
            $query->where('kyc_verifications.status', 'rejected');
        } elseif ($tab === 'expired') {
            $query->where('kyc_verifications.status', 'expired');
        }
        // tab === 'all': no status filter

        if ($search !== '') {
            $like = '%'.$search.'%';
            $query->whereHas('seller_profile.user', static function ($uq) use ($like): void {
                $uq->where('email', 'like', $like);
            });
        }

        /** @var LengthAwarePaginator<int, KycVerification> $page */
        $page = $query->paginate(20)->withQueryString();

        $rows = [];
        foreach ($page->items() as $kyc) {
            $rows[] = $this->queueRow($kyc);
        }

        return [
            'rows' => $rows,
            'pagination' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
                'from' => $page->firstItem(),
                'to' => $page->lastItem(),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function workspace(KycVerification $kyc, User $actor): array
    {
        $kyc->load([
            'seller_profile.user',
            'kycDocuments',
        ]);

        $documents = [];
        foreach ($kyc->kycDocuments as $doc) {
            $documents[] = $this->documentPayload($doc);
        }

        $history = AuditLog::query()
            ->where('target_type', AuditLogWriter::TARGET_KYC_VERIFICATION)
            ->where('target_id', $kyc->id)
            ->orderByDesc('id')
            ->limit(40)
            ->with(['actor_user' => static function ($q): void {
                $q->select(['id', 'email']);
            }])
            ->get()
            ->map(static function (AuditLog $log): array {
                return [
                    'id' => $log->id,
                    'action' => $log->action,
                    'reason_code' => $log->reason_code,
                    'actor_email' => $log->actor_user?->email,
                    'correlation_id' => $log->correlation_id,
                    'created_at' => $log->created_at?->toIso8601String(),
                    'before_json' => $log->before_json,
                    'after_json' => $log->after_json,
                ];
            })
            ->all();

        $reviewerEmail = null;
        $reviewedById = $kyc->getAttribute('reviewed_by');
        if ($reviewedById !== null && (int) $reviewedById > 0) {
            $reviewerEmail = User::query()->whereKey((int) $reviewedById)->value('email');
        }

        $status = (string) $kyc->status;
        $canVerify = $actor->hasPermissionCode(AdminPermission::SELLERS_VERIFY);

        return [
            'flags' => [
                'can_claim' => $canVerify && $status === 'submitted',
                'can_review' => $canVerify && in_array($status, ['submitted', 'under_review'], true),
                'is_terminal' => in_array($status, ['approved', 'rejected', 'expired'], true),
            ],
            'kyc' => [
                'id' => $kyc->id,
                'uuid' => $kyc->uuid,
                'status' => $kyc->status,
                'provider_ref' => $kyc->provider_ref,
                'submitted_at' => $kyc->submitted_at?->toIso8601String(),
                'reviewed_at' => $kyc->reviewed_at?->toIso8601String(),
                'rejection_reason' => $kyc->rejection_reason,
                'reviewer_email' => $reviewerEmail,
            ],
            'seller' => $kyc->seller_profile === null ? null : [
                'id' => $kyc->seller_profile->id,
                'uuid' => $kyc->seller_profile->uuid,
                'display_name' => $kyc->seller_profile->display_name,
                'legal_name' => $kyc->seller_profile->legal_name,
                'country_code' => $kyc->seller_profile->country_code,
                'default_currency' => $kyc->seller_profile->default_currency,
                'verification_status' => (string) $kyc->seller_profile->verification_status,
                'store_status' => (string) $kyc->seller_profile->store_status,
            ],
            'account' => $kyc->seller_profile?->user === null ? null : [
                'id' => $kyc->seller_profile->user->id,
                'email' => $kyc->seller_profile->user->email,
                'uuid' => $kyc->seller_profile->user->uuid,
            ],
            'documents' => $documents,
            'history' => $history,
            'routes' => [
                'claim' => route('admin.sellers.kyc.claim', ['kyc' => $kyc->id]),
                'review' => route('admin.sellers.kyc.review', ['kyc' => $kyc->id]),
                'index' => route('admin.sellers.index'),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function queueRow(KycVerification $kyc): array
    {
        $sp = $kyc->seller_profile;
        $user = $sp?->user;

        return [
            'id' => $kyc->id,
            'uuid' => $kyc->uuid,
            'status' => $kyc->status,
            'submitted_at' => $kyc->submitted_at?->toIso8601String(),
            'reviewed_at' => $kyc->reviewed_at?->toIso8601String(),
            'seller_display_name' => $sp?->display_name,
            'seller_verification_status' => $sp ? (string) $sp->verification_status : null,
            'account_email' => $user?->email,
            'workspace_url' => route('admin.sellers.kyc.show', ['kyc' => $kyc->id]),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function documentPayload(KycDocument $doc): array
    {
        $path = $this->normalizeStoragePath($doc->storage_path);
        $disk = Storage::disk('local');
        $available = $path !== null && ! str_contains($path, '..') && $disk->exists($path);

        return [
            'id' => $doc->id,
            'doc_type' => $doc->doc_type,
            'status' => $doc->status,
            'checksum_sha256' => $doc->checksum_sha256,
            'download_url' => $available ? route('admin.sellers.kyc.documents.download', ['document' => $doc->id]) : null,
        ];
    }

    private function normalizeStoragePath(?string $storagePath): ?string
    {
        if ($storagePath === null || $storagePath === '') {
            return null;
        }

        return ltrim(str_replace('\\', '/', $storagePath), '/');
    }
}
