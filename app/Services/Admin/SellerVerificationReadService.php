<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Admin\AdminPermission;
use App\Auth\RoleCodes;
use App\Models\AuditLog;
use App\Models\KycDocument;
use App\Models\KycVerification;
use App\Models\KycVerificationNote;
use App\Models\KycStatusHistory;
use App\Models\KycVerificationLog;
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
     * @return array{rows: list<array<string, mixed>>, pagination: array<string, int|string|null>, summary: array<string, int>}
     */
    public function paginatedQueue(Request $request): array
    {
        $tabRaw = (string) $request->query('tab', 'pending');
        $tab = in_array($tabRaw, ['pending', 'mine', 'escalated', 'all', 'approved', 'rejected', 'expired'], true) ? $tabRaw : 'pending';
        $search = trim((string) $request->query('q', ''));
        $actorId = (int) optional($request->user())->id;

        $query = KycVerification::query()
            ->select([
                'kyc_verifications.id',
                'kyc_verifications.uuid',
                'kyc_verifications.seller_profile_id',
                'kyc_verifications.status',
                'kyc_verifications.assigned_to_user_id',
                'kyc_verifications.assigned_at',
                'kyc_verifications.sla_due_at',
                'kyc_verifications.sla_warning_sent_at',
                'kyc_verifications.escalated_at',
                'kyc_verifications.escalation_reason',
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
                'assigned_to_user' => static function ($q): void {
                    $q->select(['id', 'email', 'uuid']);
                },
            ])
            ->orderByDesc('kyc_verifications.submitted_at');

        if ($tab === 'pending') {
            $query->whereIn('kyc_verifications.status', ['submitted', 'under_review']);
        } elseif ($tab === 'mine') {
            $query->whereIn('kyc_verifications.status', ['submitted', 'under_review'])
                ->where('kyc_verifications.assigned_to_user_id', $actorId);
        } elseif ($tab === 'escalated') {
            $query->whereIn('kyc_verifications.status', ['submitted', 'under_review'])
                ->whereNotNull('kyc_verifications.escalated_at');
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
            $query->where(static function ($q) use ($like): void {
                $q->whereHas('seller_profile.user', static function ($uq) use ($like): void {
                    $uq->where('email', 'like', $like);
                })->orWhereHas('seller_profile', static function ($sq) use ($like): void {
                    $sq->where('display_name', 'like', $like)
                        ->orWhere('legal_name', 'like', $like);
                });
            });
        }

        /** @var LengthAwarePaginator<int, KycVerification> $page */
        $page = $query->paginate(20)->withQueryString();

        $rows = [];
        foreach ($page->items() as $kyc) {
            $rows[] = $this->queueRow($kyc);
        }

        $summary = [
            'pending' => (int) KycVerification::query()->whereIn('status', ['submitted', 'under_review'])->count(),
            'mine' => $actorId > 0 ? (int) KycVerification::query()->whereIn('status', ['submitted', 'under_review'])->where('assigned_to_user_id', $actorId)->count() : 0,
            'escalated' => (int) KycVerification::query()->whereIn('status', ['submitted', 'under_review'])->whereNotNull('escalated_at')->count(),
            'approved' => (int) KycVerification::query()->where('status', 'approved')->count(),
            'rejected' => (int) KycVerification::query()->where('status', 'rejected')->count(),
        ];

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
            'summary' => $summary,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function workspace(KycVerification $kyc, User $actor): array
    {
        $kyc->load([
            'seller_profile.user',
            'assigned_to_user:id,email,uuid',
            'kycDocuments',
            'notes.user:id,email',
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

        $notes = $kyc->notes
            ->sortByDesc('created_at')
            ->map(static function (KycVerificationNote $note): array {
                return [
                    'id' => $note->id,
                    'note' => $note->note,
                    'is_private' => (bool) $note->is_private,
                    'author_email' => $note->user?->email,
                    'created_at' => $note->created_at?->toIso8601String(),
                ];
            })
            ->values()
            ->all();

        $statusHistory = class_exists(KycStatusHistory::class)
            ? KycStatusHistory::query()
                ->where('kyc_verification_id', $kyc->id)
                ->latest('id')
                ->limit(40)
                ->get()
                ->map(static fn (KycStatusHistory $row): array => [
                    'id' => $row->id,
                    'from_status' => $row->from_status,
                    'to_status' => $row->to_status,
                    'reason_code' => $row->reason_code,
                    'note' => $row->note,
                    'created_at' => $row->created_at?->toIso8601String(),
                ])
                ->values()
                ->all()
            : [];

        $providerLogs = class_exists(KycVerificationLog::class)
            ? KycVerificationLog::query()
                ->where('kyc_verification_id', $kyc->id)
                ->latest('id')
                ->limit(30)
                ->get()
                ->map(static fn (KycVerificationLog $log): array => [
                    'id' => $log->id,
                    'direction' => $log->direction,
                    'event_type' => $log->event_type,
                    'signature_status' => $log->signature_status,
                    'payload_json' => $log->payload_json,
                    'response_json' => $log->response_json,
                    'created_at' => $log->created_at?->toIso8601String(),
                ])
                ->values()
                ->all()
            : [];

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
                'is_terminal' => in_array($status, ['approved', 'verified', 'rejected', 'expired', 'resubmission_required'], true),
            ],
            'kyc' => [
                'id' => $kyc->id,
                'uuid' => $kyc->uuid,
                'status' => $kyc->status,
                'provider_ref' => $kyc->provider_ref,
                'provider_session_id' => $kyc->provider_session_id ?? null,
                'provider_session_url' => $kyc->provider_session_url ?? null,
                'provider_result_json' => $kyc->provider_result_json ?? null,
                'risk_level' => $kyc->risk_level ?? null,
                'assigned_to_user_id' => $kyc->assigned_to_user_id,
                'assigned_at' => $kyc->assigned_at?->toIso8601String(),
                'assigned_to_email' => $kyc->assigned_to_user?->email,
                'sla_due_at' => $kyc->sla_due_at?->toIso8601String(),
                'sla_warning_sent_at' => $kyc->sla_warning_sent_at?->toIso8601String(),
                'escalated_at' => $kyc->escalated_at?->toIso8601String(),
                'escalation_reason' => $kyc->escalation_reason,
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
            'notes' => $notes,
            'history' => $history,
            'status_history' => $statusHistory,
            'provider_logs' => $providerLogs,
            'reviewers' => $this->reviewers(),
            'document_insights' => $this->documentInsights($kyc),
            'routes' => [
                'claim' => route('admin.sellers.kyc.claim', ['kyc' => $kyc->id]),
                'review' => route('admin.sellers.kyc.review', ['kyc' => $kyc->id]),
                'reassign' => route('admin.sellers.kyc.reassign', ['kyc' => $kyc->id]),
                'note' => route('admin.sellers.kyc.note', ['kyc' => $kyc->id]),
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
            'assigned_at' => $kyc->assigned_at?->toIso8601String(),
            'seller_display_name' => $sp?->display_name,
            'seller_verification_status' => $sp ? (string) $sp->verification_status : null,
            'account_email' => $user?->email,
            'assigned_to_email' => $kyc->assigned_to_user?->email,
            'sla_state' => $this->slaState($kyc),
            'workspace_url' => route('admin.sellers.kyc.show', ['kyc' => $kyc->id]),
        ];
    }

    /**
     * @return list<array{value: int, label: string, email: string, roles: list<string>}>
     */
    private function reviewers(): array
    {
        return User::query()
            ->select(['id', 'email'])
            ->whereNull('deleted_at')
            ->whereHas('roles', static function ($q): void {
                $q->whereIn('roles.code', [
                    RoleCodes::SuperAdmin,
                    RoleCodes::Admin,
                    RoleCodes::Adjudicator,
                    RoleCodes::KycReviewer,
                ]);
            })
            ->with(['roles:id,code'])
            ->orderBy('email')
            ->limit(50)
            ->get()
            ->map(static function (User $user): array {
                $roles = $user->roles->pluck('code')->values()->all();

                return [
                    'value' => (int) $user->id,
                    'label' => $user->email.' · '.implode(', ', $roles),
                    'email' => (string) $user->email,
                    'roles' => $roles,
                ];
            })
            ->values()
            ->all();
    }

    private function slaState(KycVerification $kyc): string
    {
        if ($kyc->escalated_at !== null) {
            return 'breach';
        }

        $warningHours = (int) config('admin_sla.kyc.warning_hours', 12);
        $submittedAt = $kyc->submitted_at ?? $kyc->created_at;
        $ageHours = $submittedAt?->diffInHours(now()) ?? 0;

        return $ageHours >= $warningHours ? 'warning' : 'ok';
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

    /**
     * @return array<string, mixed>
     */
    private function documentInsights(KycVerification $kyc): array
    {
        $docs = $kyc->kycDocuments;
        $uploaded = $docs->count();
        $verified = $docs->where('status', 'verified')->count();
        $rejected = $docs->where('status', 'rejected')->count();

        return [
            'uploaded_count' => $uploaded,
            'verified_count' => $verified,
            'rejected_count' => $rejected,
            'quality_state' => $uploaded >= 2 && $rejected === 0 ? 'good' : ($uploaded === 0 ? 'missing' : 'review'),
            'hint' => $uploaded >= 2 ? 'Documents look complete.' : 'Request additional evidence if needed.',
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
