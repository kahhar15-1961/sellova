<?php

declare(strict_types=1);

namespace App\Services\UserSeller;

use App\Domain\Commands\UserSeller\ClaimKycForReviewCommand;
use App\Domain\Commands\UserSeller\CreateSellerProfileCommand;
use App\Domain\Commands\UserSeller\ReviewKycCommand;
use App\Domain\Commands\UserSeller\SubmitKycCommand;
use App\Domain\Commands\UserSeller\UpdateSellerProfileCommand;
use App\Domain\Commands\UserSeller\UpdateUserProfileCommand;
use App\Domain\Enums\KycVerificationStatus;
use App\Domain\Exceptions\AuthValidationFailedException;
use App\Domain\Exceptions\InvalidDomainStateTransitionException;
use App\Models\KycDocument;
use App\Models\KycVerification;
use App\Models\Product;
use App\Models\Review;
use App\Models\SellerProfile;
use App\Models\User;
use App\Models\UserPaymentMethod;
use App\Models\UserWishlistItem;
use App\Services\Audit\AuditLogWriter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class UserSellerService
{
    /**
     * @return array<string, mixed>
     */
    public function getBuyerProfile(int $userId): array
    {
        $user = User::query()->with('roles')->find($userId);
        if ($user === null) {
            throw new AuthValidationFailedException('user_not_found', ['user_id' => $userId]);
        }

        $roleCodes = $user->roles->pluck('code')->map(static fn ($code): string => (string) $code)->values()->all();

        return [
            'id' => $user->id,
            'uuid' => $user->uuid,
            'email' => $user->email,
            'phone' => $user->phone,
            'status' => $user->status,
            'risk_level' => $user->risk_level,
            'last_login_at' => $user->last_login_at?->toIso8601String(),
            'created_at' => $user->created_at?->toIso8601String(),
            'role_codes' => $roleCodes,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findSellerProfileForUser(int $userId): ?array
    {
        $profile = SellerProfile::query()->where('user_id', $userId)->first();
        if ($profile === null) {
            return null;
        }

        return $this->sellerProfileToArray($profile);
    }

    public function updateProfile(UpdateUserProfileCommand $command): array
    {
        return DB::transaction(function () use ($command): array {
            $user = User::query()->whereKey($command->userId)->lockForUpdate()->first();
            if ($user === null) {
                throw new AuthValidationFailedException('user_not_found', ['user_id' => $command->userId]);
            }

            $patch = $command->patch;
            if ($patch->email !== null) {
                if (User::query()->where('email', $patch->email)->where('id', '!=', $user->id)->exists()) {
                    throw new AuthValidationFailedException('email_taken', ['email' => $patch->email]);
                }
                $user->email = $patch->email;
            }
            if ($patch->phone !== null) {
                if (User::query()->where('phone', $patch->phone)->where('id', '!=', $user->id)->exists()) {
                    throw new AuthValidationFailedException('phone_taken', ['phone' => $patch->phone]);
                }
                $user->phone = $patch->phone;
            }
            if ($patch->passwordPlain !== null) {
                $user->password_hash = password_hash($patch->passwordPlain, PASSWORD_DEFAULT);
            }
            $user->save();

            return $this->getBuyerProfile((int) $user->id);
        });
    }

    public function updateSellerProfile(UpdateSellerProfileCommand $command): array
    {
        return DB::transaction(function () use ($command): array {
            $profile = SellerProfile::query()->where('user_id', $command->ownerUserId)->lockForUpdate()->first();
            if ($profile === null) {
                throw new AuthValidationFailedException('seller_profile_not_found', ['user_id' => $command->ownerUserId]);
            }
            if ($command->displayName !== null) {
                $profile->display_name = $command->displayName;
            }
            if ($command->legalName !== null) {
                $profile->legal_name = $command->legalName;
            }
            $profile->save();

            return $this->sellerProfileToArray($profile);
        });
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listBuyerPaymentMethods(int $userId): array
    {
        return UserPaymentMethod::query()
            ->where('user_id', $userId)
            ->orderByDesc('is_default')
            ->orderByDesc('id')
            ->get()
            ->map(fn (UserPaymentMethod $m): array => $this->paymentMethodToArray($m))
            ->values()
            ->all();
    }

    /**
     * @param  array{kind?: string, label?: string, subtitle?: string, is_default?: bool}  $payload
     * @return array<string, mixed>
     */
    public function createBuyerPaymentMethod(int $userId, array $payload): array
    {
        return DB::transaction(function () use ($userId, $payload): array {
            $kind = strtolower(trim((string) ($payload['kind'] ?? 'card')));
            $label = trim((string) ($payload['label'] ?? ''));
            $subtitle = trim((string) ($payload['subtitle'] ?? ''));
            $isDefault = (bool) ($payload['is_default'] ?? false);

            if ($label === '') {
                throw new AuthValidationFailedException('validation_failed', ['label' => 'required']);
            }

            if (! in_array($kind, ['card', 'bkash', 'nagad', 'bank'], true)) {
                $kind = 'card';
            }

            $exists = UserPaymentMethod::query()->where('user_id', $userId)->exists();
            if (! $exists) {
                $isDefault = true;
            }

            if ($isDefault) {
                UserPaymentMethod::query()->where('user_id', $userId)->update(['is_default' => false]);
            }

            $method = UserPaymentMethod::query()->create([
                'uuid' => (string) Str::uuid(),
                'user_id' => $userId,
                'kind' => $kind,
                'label' => $label,
                'subtitle' => $subtitle !== '' ? $subtitle : null,
                'is_default' => $isDefault,
            ]);

            return $this->paymentMethodToArray($method);
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function setDefaultBuyerPaymentMethod(int $userId, int $paymentMethodId): array
    {
        return DB::transaction(function () use ($userId, $paymentMethodId): array {
            /** @var UserPaymentMethod|null $method */
            $method = UserPaymentMethod::query()
                ->where('user_id', $userId)
                ->where('id', $paymentMethodId)
                ->lockForUpdate()
                ->first();

            if ($method === null) {
                throw new AuthValidationFailedException('not_found', ['payment_method_id' => $paymentMethodId]);
            }

            UserPaymentMethod::query()->where('user_id', $userId)->update(['is_default' => false]);
            $method->is_default = true;
            $method->save();

            return $this->paymentMethodToArray($method);
        });
    }

    public function deleteBuyerPaymentMethod(int $userId, int $paymentMethodId): void
    {
        DB::transaction(function () use ($userId, $paymentMethodId): void {
            /** @var UserPaymentMethod|null $method */
            $method = UserPaymentMethod::query()
                ->where('user_id', $userId)
                ->where('id', $paymentMethodId)
                ->lockForUpdate()
                ->first();

            if ($method === null) {
                return;
            }

            $wasDefault = (bool) $method->is_default;
            $method->delete();

            if ($wasDefault) {
                /** @var UserPaymentMethod|null $next */
                $next = UserPaymentMethod::query()->where('user_id', $userId)->orderByDesc('id')->first();
                if ($next !== null) {
                    $next->is_default = true;
                    $next->save();
                }
            }
        });
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listBuyerWishlist(int $userId): array
    {
        return UserWishlistItem::query()
            ->with('product')
            ->where('user_id', $userId)
            ->orderByDesc('id')
            ->get()
            ->map(fn (UserWishlistItem $i): array => $this->wishlistToArray($i))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function addBuyerWishlistItem(int $userId, int $productId): array
    {
        return DB::transaction(function () use ($userId, $productId): array {
            /** @var Product|null $product */
            $product = Product::query()->whereKey($productId)->first();
            if ($product === null) {
                throw new AuthValidationFailedException('product_not_found', ['product_id' => $productId]);
            }

            /** @var UserWishlistItem|null $existing */
            $existing = UserWishlistItem::query()
                ->with('product')
                ->where('user_id', $userId)
                ->where('product_id', $productId)
                ->first();

            if ($existing !== null) {
                return $this->wishlistToArray($existing);
            }

            $item = UserWishlistItem::query()->create([
                'uuid' => (string) Str::uuid(),
                'user_id' => $userId,
                'product_id' => $productId,
            ]);
            $item->load('product');

            return $this->wishlistToArray($item);
        });
    }

    public function removeBuyerWishlistItem(int $userId, int $productId): void
    {
        UserWishlistItem::query()
            ->where('user_id', $userId)
            ->where('product_id', $productId)
            ->delete();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listBuyerReviews(int $userId): array
    {
        return Review::query()
            ->with(['product', 'order_item.order'])
            ->where('buyer_user_id', $userId)
            ->orderByDesc('id')
            ->get()
            ->map(fn (Review $r): array => $this->buyerReviewToArray($r))
            ->values()
            ->all();
    }

    public function createSellerProfile(CreateSellerProfileCommand $command): array
    {
        throw new \LogicException('Not implemented.');
    }

    public function submitKyc(SubmitKycCommand $command): array
    {
        throw new \LogicException('Not implemented.');
    }

    public function claimKycForReview(ClaimKycForReviewCommand $command): array
    {
        return DB::transaction(function () use ($command): array {
            /** @var KycVerification|null $kyc */
            $kyc = KycVerification::query()->whereKey($command->kycVerificationId)->lockForUpdate()->first();
            if ($kyc === null) {
                throw new AuthValidationFailedException('kyc_not_found', ['kyc_verification_id' => $command->kycVerificationId]);
            }

            $before = $this->kycSnapshot($kyc);

            if ($kyc->status === KycVerificationStatus::UnderReview->value) {
                return $this->kycReviewResult($kyc->fresh(['seller_profile.user']), $before, $before);
            }

            if ($kyc->status !== KycVerificationStatus::Submitted->value) {
                throw new InvalidDomainStateTransitionException(
                    'kyc_verification',
                    $kyc->id,
                    (string) $kyc->status,
                    KycVerificationStatus::UnderReview->value,
                    'Only submitted cases can be claimed for review.',
                );
            }

            $kyc->status = KycVerificationStatus::UnderReview->value;
            $kyc->save();

            $after = $this->kycSnapshot($kyc);

            AuditLogWriter::write(
                $command->reviewerId,
                AuditLogWriter::ACTION_KYC_CLAIMED,
                AuditLogWriter::TARGET_KYC_VERIFICATION,
                (int) $kyc->id,
                $before,
                $after,
                null,
                $command->correlationId,
                $command->ipAddress,
                $command->userAgent,
            );

            return $this->kycReviewResult($kyc->fresh(['seller_profile.user']), $before, $after);
        });
    }

    public function reviewKyc(ReviewKycCommand $command): array
    {
        return DB::transaction(function () use ($command): array {
            /** @var KycVerification|null $kyc */
            $kyc = KycVerification::query()->whereKey($command->kycVerificationId)->lockForUpdate()->first();
            if ($kyc === null) {
                throw new AuthValidationFailedException('kyc_not_found', ['kyc_verification_id' => $command->kycVerificationId]);
            }

            /** @var SellerProfile|null $seller */
            $seller = SellerProfile::query()->whereKey($kyc->seller_profile_id)->lockForUpdate()->first();
            if ($seller === null) {
                throw new AuthValidationFailedException('seller_profile_not_found', ['seller_profile_id' => $kyc->seller_profile_id]);
            }

            $decision = strtolower(trim($command->decision));
            if (! in_array($decision, ['approved', 'rejected'], true)) {
                throw new InvalidDomainStateTransitionException(
                    'kyc_verification',
                    $kyc->id,
                    (string) $kyc->status,
                    $decision,
                    'Decision must be approved or rejected.',
                );
            }

            $targetStatus = $decision === 'approved'
                ? KycVerificationStatus::Approved->value
                : KycVerificationStatus::Rejected->value;

            $beforeKyc = $this->kycSnapshot($kyc);
            $beforeSeller = $this->sellerSnapshot($seller);

            // Idempotent replay: same terminal decision
            if ($kyc->status === KycVerificationStatus::Approved->value && $decision === 'approved') {
                return $this->kycReviewResult($kyc->fresh(['seller_profile.user']), $beforeKyc, $beforeKyc);
            }
            if ($kyc->status === KycVerificationStatus::Rejected->value && $decision === 'rejected') {
                return $this->kycReviewResult($kyc->fresh(['seller_profile.user']), $beforeKyc, $beforeKyc);
            }

            if (in_array($kyc->status, [KycVerificationStatus::Approved->value, KycVerificationStatus::Rejected->value], true)) {
                throw new InvalidDomainStateTransitionException(
                    'kyc_verification',
                    $kyc->id,
                    (string) $kyc->status,
                    $targetStatus,
                    'Case is already terminal; conflicting decision.',
                );
            }

            if (! in_array($kyc->status, [KycVerificationStatus::Submitted->value, KycVerificationStatus::UnderReview->value], true)) {
                throw new InvalidDomainStateTransitionException(
                    'kyc_verification',
                    $kyc->id,
                    (string) $kyc->status,
                    $targetStatus,
                    'Case is not in a reviewable state.',
                );
            }

            $kyc->status = $targetStatus;
            $kyc->reviewed_by = $command->reviewerId;
            $kyc->reviewed_at = now();
            $kyc->rejection_reason = $decision === 'rejected' ? ($command->reason ?? '') : null;
            $kyc->save();

            $seller->verification_status = $decision === 'approved' ? 'verified' : 'rejected';
            $seller->save();

            $docStatus = $decision === 'approved' ? 'verified' : 'rejected';
            KycDocument::query()->where('kyc_verification_id', $kyc->id)->update(['status' => $docStatus]);

            $afterKyc = $this->kycSnapshot($kyc->fresh());
            $afterSeller = $this->sellerSnapshot($seller->fresh());

            AuditLogWriter::write(
                $command->reviewerId,
                AuditLogWriter::ACTION_KYC_REVIEWED,
                AuditLogWriter::TARGET_KYC_VERIFICATION,
                (int) $kyc->id,
                array_merge($beforeKyc, ['seller_profile' => $beforeSeller]),
                array_merge($afterKyc, ['seller_profile' => $afterSeller]),
                $decision === 'rejected' ? 'kyc_rejected' : 'kyc_approved',
                $command->correlationId,
                $command->ipAddress,
                $command->userAgent,
            );

            return $this->kycReviewResult($kyc->fresh(['seller_profile.user']), $beforeKyc, $afterKyc);
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function kycSnapshot(KycVerification $kyc): array
    {
        return [
            'id' => $kyc->id,
            'uuid' => $kyc->uuid,
            'status' => $kyc->status,
            'reviewed_by' => $kyc->reviewed_by,
            'reviewed_at' => $kyc->reviewed_at?->toIso8601String(),
            'rejection_reason' => $kyc->rejection_reason,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function sellerSnapshot(SellerProfile $seller): array
    {
        return [
            'id' => $seller->id,
            'verification_status' => (string) $seller->verification_status,
            'store_status' => (string) $seller->store_status,
        ];
    }

    /**
     * @param  array<string, mixed>  $beforeKyc
     * @param  array<string, mixed>  $afterKyc
     * @return array<string, mixed>
     */
    private function kycReviewResult(KycVerification $kyc, array $beforeKyc, array $afterKyc): array
    {
        return [
            'kyc_verification' => [
                'id' => $kyc->id,
                'uuid' => $kyc->uuid,
                'status' => $kyc->status,
                'reviewed_by' => $kyc->reviewed_by,
                'reviewed_at' => $kyc->reviewed_at?->toIso8601String(),
                'rejection_reason' => $kyc->rejection_reason,
                'submitted_at' => $kyc->submitted_at?->toIso8601String(),
            ],
            'seller_profile' => $kyc->seller_profile !== null ? $this->sellerProfileToArray($kyc->seller_profile) : null,
            'audit' => [
                'before' => $beforeKyc,
                'after' => $afterKyc,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function sellerProfileToArray(SellerProfile $profile): array
    {
        return [
            'id' => $profile->id,
            'uuid' => $profile->uuid,
            'user_id' => $profile->user_id,
            'display_name' => $profile->display_name,
            'legal_name' => $profile->legal_name,
            'country_code' => $profile->country_code,
            'default_currency' => $profile->default_currency,
            'verification_status' => (string) $profile->verification_status,
            'store_status' => (string) $profile->store_status,
            'created_at' => $profile->created_at?->toIso8601String(),
            'updated_at' => $profile->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function paymentMethodToArray(UserPaymentMethod $m): array
    {
        return [
            'id' => (int) $m->id,
            'uuid' => $m->uuid,
            'kind' => (string) $m->kind,
            'label' => (string) $m->label,
            'subtitle' => (string) ($m->subtitle ?? ''),
            'is_default' => (bool) $m->is_default,
            'created_at' => $m->created_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function wishlistToArray(UserWishlistItem $item): array
    {
        $product = $item->product;
        $currency = strtoupper((string) ($product?->currency ?? 'USD'));
        $basePrice = $product?->base_price;
        $priceLabel = $basePrice !== null ? trim($currency.' '.$basePrice) : $currency;

        return [
            'id' => (int) $item->id,
            'uuid' => $item->uuid,
            'product_id' => (int) $item->product_id,
            'name' => (string) ($product?->title ?? 'Product'),
            'price_label' => $priceLabel,
            'image_url' => null,
            'created_at' => $item->created_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buyerReviewToArray(Review $r): array
    {
        $orderNo = $r->order_item?->order?->order_number ?? '—';
        $createdAt = $r->created_at;
        return [
            'id' => (int) $r->id,
            'order_no' => (string) $orderNo,
            'product_id' => (int) $r->product_id,
            'product_name' => (string) ($r->product?->title ?? $r->order_item?->title_snapshot ?? 'Product'),
            'rating' => (int) $r->rating,
            'comment' => (string) ($r->comment ?? ''),
            'status' => (string) $r->status,
            'created_at' => $createdAt?->toIso8601String(),
            'created_at_label' => $createdAt?->format('d M Y') ?? '',
        ];
    }
}
