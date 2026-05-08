<?php

declare(strict_types=1);

namespace App\Services\UserSeller;

use App\Domain\Commands\UserSeller\ClaimKycForReviewCommand;
use App\Domain\Commands\UserSeller\BulkClaimKycForReviewCommand;
use App\Domain\Commands\UserSeller\CreateSellerProfileCommand;
use App\Domain\Commands\UserSeller\ReassignKycForReviewCommand;
use App\Domain\Commands\UserSeller\ReviewKycCommand;
use App\Domain\Commands\UserSeller\SubmitKycCommand;
use App\Domain\Commands\UserSeller\UpdateSellerProfileCommand;
use App\Domain\Commands\UserSeller\UpdateUserProfileCommand;
use App\Auth\RoleCodes;
use App\Events\UserNotificationCreated;
use App\Domain\Enums\KycVerificationStatus;
use App\Domain\Exceptions\AuthValidationFailedException;
use App\Domain\Exceptions\InvalidDomainStateTransitionException;
use App\Models\KycDocument;
use App\Models\KycVerification;
use App\Models\Notification;
use App\Models\Order;
use App\Models\PayoutAccount;
use App\Models\Product;
use App\Models\Review;
use App\Models\SellerShippingMethod;
use App\Models\ShippingMethod;
use App\Models\SellerProfile;
use App\Models\AdminEscalationPolicy;
use App\Models\AdminOnCallRotation;
use App\Models\User;
use App\Models\UserNotificationPreference;
use App\Models\UserPaymentMethod;
use App\Models\UserWishlistItem;
use App\Services\PushNotification\PushNotificationService;
use App\Services\Audit\AuditLogWriter;
use Illuminate\Database\QueryException;
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
            'display_name' => $user->display_name ?? null,
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

        return $this->sellerProfileToArrayWithKyc($profile);
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
            if ($patch->displayName !== null) {
                $user->display_name = $patch->displayName;
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
            if ($command->storeLogoUrl !== null) {
                $profile->store_logo_url = $command->storeLogoUrl;
            }
            if ($command->bannerImageUrl !== null) {
                $profile->banner_image_url = $command->bannerImageUrl;
            }
            if ($command->contactEmail !== null) {
                $profile->contact_email = $command->contactEmail;
            }
            if ($command->contactPhone !== null) {
                $profile->contact_phone = $command->contactPhone;
            }
            if ($command->addressLine !== null) {
                $profile->address_line = $command->addressLine;
            }
            if ($command->city !== null) {
                $profile->city = $command->city;
            }
            if ($command->region !== null) {
                $profile->region = $command->region;
            }
            if ($command->postalCode !== null) {
                $profile->postal_code = $command->postalCode;
            }
            if ($command->country !== null) {
                $profile->country = $command->country;
            }
            $profile->save();

            return $this->sellerProfileToArray($profile->fresh(['user']));
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function getSellerShippingSettings(int $userId): array
    {
        $profile = $this->sellerProfileForUserOrFail($userId);
        $profile->load(['shippingMethods.shippingMethod']);

        return $this->sellerShippingSettingsToArray($profile);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function updateSellerShippingSettings(int $userId, array $payload): array
    {
        return DB::transaction(function () use ($userId, $payload): array {
            $profile = SellerProfile::query()->where('user_id', $userId)->lockForUpdate()->first();
            if ($profile === null) {
                throw new AuthValidationFailedException('seller_profile_not_found', ['user_id' => $userId]);
            }

            $profile->cash_on_delivery_enabled = (bool) ($payload['cash_on_delivery_enabled'] ?? true);
            $profile->processing_time_label = $this->stringOrDefault($payload['processing_time_label'] ?? null, '1-2 Business Days', 80);
            $profile->save();

            $shippingMethods = is_array($payload['shipping_methods'] ?? null) ? $payload['shipping_methods'] : [];
            if ($shippingMethods === [] && isset($payload['inside_dhaka_label'], $payload['outside_dhaka_label'])) {
                $shippingMethods = [
                    [
                        'method_name' => $payload['inside_dhaka_label'],
                        'price' => $payload['inside_dhaka_fee'] ?? null,
                        'processing_time_label' => $profile->processing_time_label,
                    ],
                    [
                        'method_name' => $payload['outside_dhaka_label'],
                        'price' => $payload['outside_dhaka_fee'] ?? null,
                        'processing_time_label' => $profile->processing_time_label,
                    ],
                ];
            }

            $seen = [];
            foreach ($shippingMethods as $index => $item) {
                if (! is_array($item)) {
                    continue;
                }
                $methodId = isset($item['shipping_method_id']) && is_numeric($item['shipping_method_id'])
                    ? (int) $item['shipping_method_id']
                    : null;
                if ($methodId === null && isset($item['method_name'])) {
                    $methodId = $this->shippingMethodIdForName((string) $item['method_name']);
                }
                if ($methodId === null || $methodId < 1) {
                    continue;
                }
                $method = ShippingMethod::query()->whereKey($methodId)->where('is_active', true)->first();
                if ($method === null) {
                    continue;
                }
                $seen[] = $methodId;
                SellerShippingMethod::query()->updateOrCreate(
                    ['seller_profile_id' => $profile->id, 'shipping_method_id' => $methodId],
                    [
                        'price' => $this->moneyOrDefault($item['price'] ?? $item['fee'] ?? $method->suggested_fee, (float) $method->suggested_fee),
                        'processing_time_label' => $this->stringOrDefault($item['processing_time_label'] ?? $method->processing_time_label, $method->processing_time_label, 80),
                        'is_enabled' => (bool) ($item['is_enabled'] ?? true),
                        'sort_order' => (int) ($item['sort_order'] ?? (($index + 1) * 10)),
                    ],
                );
            }
            if ($seen !== []) {
                SellerShippingMethod::query()
                    ->where('seller_profile_id', $profile->id)
                    ->whereNotIn('shipping_method_id', $seen)
                    ->update(['is_enabled' => false]);
                $firstTwo = SellerShippingMethod::query()
                    ->with('shippingMethod')
                    ->where('seller_profile_id', $profile->id)
                    ->where('is_enabled', true)
                    ->orderBy('sort_order')
                    ->limit(2)
                    ->get();
                $first = $firstTwo->get(0);
                $second = $firstTwo->get(1);
                $profile->inside_dhaka_label = $first?->shippingMethod?->name;
                $profile->inside_dhaka_fee = $first?->price;
                $profile->outside_dhaka_label = $second?->shippingMethod?->name;
                $profile->outside_dhaka_fee = $second?->price;
                $profile->save();
            }

            $profile->load(['shippingMethods.shippingMethod']);
            return $this->sellerShippingSettingsToArray($profile);
        });
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listSellerPayoutMethods(int $userId): array
    {
        $profile = $this->sellerProfileForUserOrFail($userId);

        return PayoutAccount::query()
            ->where('seller_profile_id', (int) $profile->id)
            ->orderByDesc('is_default')
            ->orderByDesc('id')
            ->get()
            ->map(fn (PayoutAccount $account): array => $this->payoutAccountToArray($account))
            ->values()
            ->all();
    }

    /**
     * @param array<string, mixed> $payload
     * @return list<array<string, mixed>>
     */
    public function upsertSellerPayoutMethod(int $userId, array $payload): array
    {
        return DB::transaction(function () use ($userId, $payload): array {
            $profile = SellerProfile::query()->where('user_id', $userId)->lockForUpdate()->first();
            if ($profile === null) {
                throw new AuthValidationFailedException('seller_profile_not_found', ['user_id' => $userId]);
            }

            $type = strtolower(trim((string) ($payload['method_type'] ?? '')));
            if (! in_array($type, ['bkash', 'nagad', 'bank_transfer'], true)) {
                throw new AuthValidationFailedException('validation_failed', ['method_type' => 'invalid']);
            }
            $accountName = trim((string) ($payload['account_name'] ?? ''));
            $accountNumber = trim((string) ($payload['account_number'] ?? ''));
            if ($accountName === '' || $accountNumber === '') {
                throw new AuthValidationFailedException('validation_failed', ['account' => 'required']);
            }
            $bankName = trim((string) ($payload['bank_name'] ?? ''));
            $branchName = trim((string) ($payload['branch_name'] ?? ''));
            $routingNumber = trim((string) ($payload['routing_number'] ?? ''));
            $accountTypeLabel = trim((string) ($payload['account_type_label'] ?? ''));
            $asDefault = (bool) ($payload['is_default'] ?? false);

            if ($asDefault) {
                PayoutAccount::query()
                    ->where('seller_profile_id', (int) $profile->id)
                    ->update(['is_default' => false]);
            }

            PayoutAccount::query()->create([
                'seller_profile_id' => (int) $profile->id,
                'account_type' => $type === 'bank_transfer' ? 'bank' : 'mobile_money',
                'provider' => $type === 'bank_transfer' ? ($bankName !== '' ? $bankName : 'bank') : $type,
                'account_ref_token' => json_encode([
                    'method_type' => $type,
                    'account_name' => $accountName,
                    'account_number' => $accountNumber,
                    'bank_name' => $bankName !== '' ? $bankName : null,
                    'branch_name' => $branchName !== '' ? $branchName : null,
                    'routing_number' => $routingNumber !== '' ? $routingNumber : null,
                    'account_type_label' => $accountTypeLabel !== '' ? $accountTypeLabel : null,
                ], JSON_THROW_ON_ERROR),
                'is_default' => $asDefault,
                'status' => 'active',
            ]);

            return $this->listSellerPayoutMethods($userId);
        });
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function deleteSellerPayoutMethod(int $userId, int $payoutMethodId): array
    {
        return DB::transaction(function () use ($userId, $payoutMethodId): array {
            $profile = SellerProfile::query()->where('user_id', $userId)->lockForUpdate()->first();
            if ($profile === null) {
                throw new AuthValidationFailedException('seller_profile_not_found', ['user_id' => $userId]);
            }

            $account = PayoutAccount::query()
                ->where('seller_profile_id', (int) $profile->id)
                ->whereKey($payoutMethodId)
                ->lockForUpdate()
                ->first();
            if ($account === null) {
                throw new AuthValidationFailedException('not_found', ['payout_method_id' => $payoutMethodId]);
            }
            $wasDefault = (bool) $account->is_default;
            $account->delete();

            if ($wasDefault) {
                $next = PayoutAccount::query()
                    ->where('seller_profile_id', (int) $profile->id)
                    ->orderByDesc('id')
                    ->first();
                if ($next !== null) {
                    $next->is_default = true;
                    $next->save();
                }
            }

            return $this->listSellerPayoutMethods($userId);
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
     * @param  array{kind?: string, label?: string, subtitle?: string, details?: array<string, mixed>, is_default?: bool}  $payload
     * @return array<string, mixed>
     */
    public function createBuyerPaymentMethod(int $userId, array $payload): array
    {
        return DB::transaction(function () use ($userId, $payload): array {
            return $this->persistBuyerPaymentMethod($userId, null, $payload);
        });
    }

    /**
     * @param  array{kind?: string, label?: string, subtitle?: string, details?: array<string, mixed>, is_default?: bool}  $payload
     * @return array<string, mixed>
     */
    public function updateBuyerPaymentMethod(int $userId, int $paymentMethodId, array $payload): array
    {
        return DB::transaction(function () use ($userId, $paymentMethodId, $payload): array {
            /** @var UserPaymentMethod|null $method */
            $method = UserPaymentMethod::query()
                ->where('user_id', $userId)
                ->where('id', $paymentMethodId)
                ->lockForUpdate()
                ->first();

            if ($method === null) {
                throw new AuthValidationFailedException('not_found', ['payment_method_id' => $paymentMethodId]);
            }

            return $this->persistBuyerPaymentMethod($userId, $method, $payload);
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

    /**
     * @return array<string, mixed>
     */
    public function createBuyerReview(int $userId, int $orderId, int $rating, string $comment): array
    {
        $rating = max(1, min(5, $rating));
        $comment = trim(mb_substr($comment, 0, 500));

        return DB::transaction(function () use ($userId, $orderId, $rating, $comment): array {
            $order = Order::query()
                ->with(['orderItems'])
                ->whereKey($orderId)
                ->where('buyer_user_id', $userId)
                ->lockForUpdate()
                ->first();

            if ($order === null) {
                throw new AuthValidationFailedException('order_not_found', ['order_id' => $orderId]);
            }

            if ((string) $order->status->value !== 'completed') {
                throw new AuthValidationFailedException('review_requires_completed_order', [
                    'order_id' => $orderId,
                    'status' => $order->status->value,
                ]);
            }

            $item = $order->orderItems->first();
            if ($item === null) {
                throw new AuthValidationFailedException('order_item_not_found', ['order_id' => $orderId]);
            }

            $review = Review::query()->updateOrCreate(
                [
                    'order_item_id' => (int) $item->id,
                    'buyer_user_id' => $userId,
                ],
                [
                    'uuid' => (string) Str::uuid(),
                    'seller_profile_id' => (int) $item->seller_profile_id,
                    'product_id' => (int) $item->product_id,
                    'rating' => $rating,
                    'comment' => $comment,
                    'status' => 'visible',
                ],
            );

            return $this->buyerReviewToArray($review->fresh(['product', 'order_item.order']));
        });
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listSellerReviews(int $userId): array
    {
        $profile = $this->sellerProfileForUserOrFail($userId);

        return Review::query()
            ->with(['buyer', 'product', 'order_item.order'])
            ->where('seller_profile_id', (int) $profile->id)
            ->orderByDesc('id')
            ->get()
            ->map(fn (Review $r): array => $this->sellerReviewToArray($r))
            ->values()
            ->all();
    }

    /**
     * @return array{items: list<array<string, mixed>>, unread_count: int}
     */
    public function listBuyerNotifications(int $userId): array
    {
        $items = \App\Models\Notification::query()
            ->where('user_id', $userId)
            ->orderByDesc('id')
            ->limit(100)
            ->get()
            ->map(fn (\App\Models\Notification $n): array => $this->notificationToArray($n))
            ->values()
            ->all();

        $unreadCount = \App\Models\Notification::query()
            ->where('user_id', $userId)
            ->whereNull('read_at')
            ->count();

        return ['items' => $items, 'unread_count' => $unreadCount];
    }

    /**
     * @return array<string, mixed>
     */
    public function markBuyerNotificationRead(int $userId, int $notificationId): array
    {
        /** @var \App\Models\Notification|null $notification */
        $notification = \App\Models\Notification::query()
            ->where('user_id', $userId)
            ->where('id', $notificationId)
            ->first();
        if ($notification === null) {
            throw new AuthValidationFailedException('not_found', ['notification_id' => $notificationId]);
        }
        if ($notification->read_at === null) {
            $notification->read_at = now();
            $notification->status = 'read';
            $notification->save();
        }

        return $this->notificationToArray($notification);
    }

    public function markAllBuyerNotificationsRead(int $userId): int
    {
        return \App\Models\Notification::query()
            ->where('user_id', $userId)
            ->whereNull('read_at')
            ->update([
                'read_at' => now(),
                'status' => 'read',
                'updated_at' => now(),
            ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function getBuyerNotificationPreferences(int $userId): array
    {
        try {
            /** @var UserNotificationPreference $pref */
            $pref = UserNotificationPreference::query()->firstOrCreate(
                ['user_id' => $userId],
                [
                    'in_app_enabled' => true,
                    'email_enabled' => true,
                    'order_updates_enabled' => true,
                    'promotion_enabled' => true,
                ]
            );

            return $this->notificationPreferenceToArray($pref);
        } catch (QueryException) {
            return [
                'in_app_enabled' => true,
                'email_enabled' => true,
                'order_updates_enabled' => true,
                'promotion_enabled' => true,
                'updated_at' => null,
            ];
        }
    }

    /**
     * @param  array{in_app_enabled?: bool, email_enabled?: bool, order_updates_enabled?: bool, promotion_enabled?: bool}  $payload
     * @return array<string, mixed>
     */
    public function updateBuyerNotificationPreferences(int $userId, array $payload): array
    {
        try {
            /** @var UserNotificationPreference $pref */
            $pref = UserNotificationPreference::query()->firstOrCreate(
                ['user_id' => $userId],
                [
                    'in_app_enabled' => true,
                    'email_enabled' => true,
                    'order_updates_enabled' => true,
                    'promotion_enabled' => true,
                ]
            );

            foreach (['in_app_enabled', 'email_enabled', 'order_updates_enabled', 'promotion_enabled'] as $field) {
                if (array_key_exists($field, $payload)) {
                    $pref->{$field} = (bool) $payload[$field];
                }
            }
            $pref->save();

            return $this->notificationPreferenceToArray($pref);
        } catch (QueryException) {
            return [
                'in_app_enabled' => (bool) ($payload['in_app_enabled'] ?? true),
                'email_enabled' => (bool) ($payload['email_enabled'] ?? true),
                'order_updates_enabled' => (bool) ($payload['order_updates_enabled'] ?? true),
                'promotion_enabled' => (bool) ($payload['promotion_enabled'] ?? true),
                'updated_at' => null,
            ];
        }
    }

    public function createSellerProfile(CreateSellerProfileCommand $command): array
    {
        return DB::transaction(function () use ($command): array {
            $profile = SellerProfile::query()->where('user_id', $command->userId)->lockForUpdate()->first();
            if ($profile !== null) {
                $profile->display_name = $command->draft->displayName;
                $profile->legal_name = $command->draft->legalName;
                $profile->country_code = $command->draft->countryCode;
                $profile->default_currency = $command->draft->defaultCurrency;
                $profile->save();
            } else {
                $profile = SellerProfile::query()->create([
                    'uuid' => (string) Str::uuid(),
                    'user_id' => $command->userId,
                    'display_name' => $command->draft->displayName,
                    'legal_name' => $command->draft->legalName,
                    'country_code' => $command->draft->countryCode,
                    'default_currency' => $command->draft->defaultCurrency,
                    'verification_status' => 'unverified',
                    'store_status' => 'active',
                ]);
            }

            $this->notifyUser(
                userId: $command->userId,
                templateCode: 'seller.profile.created',
                payload: [
                    'title' => 'Seller profile created',
                    'body' => 'Your seller profile is ready for KYC verification.',
                    'href' => '/seller/onboarding',
                    'seller_profile_id' => $profile->id,
                    'display_name' => $profile->display_name,
                ],
            );

            return $this->sellerProfileToArrayWithKyc($profile->fresh());
        });
    }

    public function submitKyc(SubmitKycCommand $command): array
    {
        if ($command->documents === []) {
            throw new AuthValidationFailedException('kyc_documents_required', ['seller_profile_id' => $command->sellerProfileId]);
        }

        return DB::transaction(function () use ($command): array {
            $profile = SellerProfile::query()->whereKey($command->sellerProfileId)->lockForUpdate()->first();
            if ($profile === null) {
                throw new AuthValidationFailedException('seller_profile_not_found', ['seller_profile_id' => $command->sellerProfileId]);
            }

            $active = KycVerification::query()
                ->where('seller_profile_id', $profile->id)
                ->whereIn('status', [KycVerificationStatus::Submitted->value, KycVerificationStatus::UnderReview->value])
                ->orderByDesc('id')
                ->first();
            if ($active !== null) {
                return $this->sellerProfileToArrayWithKyc($profile->fresh(), $active->fresh(['kycDocuments']));
            }

            $docs = [];
            $seen = [];
            foreach ($command->documents as $item) {
                $docType = strtolower(trim($item->docType));
                if (isset($seen[$docType])) {
                    continue;
                }
                $seen[$docType] = true;
                $docs[] = [
                    'doc_type' => $docType,
                    'storage_path' => trim($item->storagePath),
                    'checksum_sha256' => trim($item->checksumSha256),
                ];
            }
            if ($docs === []) {
                throw new AuthValidationFailedException('kyc_documents_required', ['seller_profile_id' => $command->sellerProfileId]);
            }

            $kyc = KycVerification::query()->create([
                'uuid' => (string) Str::uuid(),
                'seller_profile_id' => $profile->id,
                'status' => KycVerificationStatus::Submitted->value,
                'provider_ref' => 'seller-kyc-'.$profile->id.'-'.Str::lower(Str::random(8)),
                'submitted_at' => now(),
                'sla_due_at' => now()->addHours((int) config('admin_sla.kyc.breach_hours', 24)),
                'sla_warning_sent_at' => null,
                'escalated_at' => null,
                'escalation_reason' => null,
            ]);

            foreach ($docs as $doc) {
                KycDocument::query()->create([
                    'kyc_verification_id' => $kyc->id,
                    'doc_type' => $doc['doc_type'],
                    'storage_path' => $doc['storage_path'],
                    'checksum_sha256' => $doc['checksum_sha256'],
                    'status' => 'uploaded',
                ]);
            }

            $profile->verification_status = 'pending';
            $profile->save();

            $assigneeId = $this->resolveKycAssigneeId();
            if ($assigneeId !== null) {
                $kyc->assigned_to_user_id = $assigneeId;
                $kyc->assigned_at = now();
                $kyc->status = KycVerificationStatus::UnderReview->value;
                $kyc->save();
            }

            AuditLogWriter::write(
                (int) $profile->user_id,
                'kyc.verification.submitted',
                AuditLogWriter::TARGET_KYC_VERIFICATION,
                (int) $kyc->id,
                [],
                $this->kycSnapshot($kyc->fresh(['kycDocuments'])),
                'kyc_submitted',
                (string) Str::uuid(),
                null,
                null,
            );

            $this->notifyUser(
                userId: (int) $profile->user_id,
                templateCode: 'seller.kyc.submitted',
                payload: [
                    'title' => 'KYC submitted',
                    'body' => 'Your verification case is now waiting for admin review.',
                    'href' => '/seller/kyc',
                    'seller_profile_id' => $profile->id,
                    'kyc_id' => $kyc->id,
                    'status' => $kyc->status,
                ],
            );

            $this->notifyStaff(
                templateCode: 'admin.kyc.submitted',
                payload: [
                    'title' => 'New KYC submission',
                    'body' => $profile->display_name.' submitted a verification case for review.',
                    'href' => route('admin.sellers.kyc.show', ['kyc' => $kyc->id]),
                    'seller_profile_id' => $profile->id,
                    'kyc_id' => $kyc->id,
                    'assigned_to_user_id' => $assigneeId,
                ],
            );

            if ($assigneeId !== null) {
                $this->notifyUser(
                    userId: $assigneeId,
                    templateCode: 'admin.kyc.assigned',
                    payload: [
                        'title' => 'KYC assigned',
                        'body' => 'You have been assigned a new seller verification case.',
                        'href' => route('admin.sellers.kyc.show', ['kyc' => $kyc->id]),
                        'seller_profile_id' => $profile->id,
                        'kyc_id' => $kyc->id,
                    ],
                );
            }

            return $this->sellerProfileToArrayWithKyc($profile->fresh(), $kyc->fresh(['kycDocuments']));
        });
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

            if ($kyc->assigned_to_user_id !== null
                && (int) $kyc->assigned_to_user_id !== $command->reviewerId
                && ! $this->hasElevatedKycReviewAccess($command->reviewerId)) {
                throw new AuthValidationFailedException('kyc_assignee_mismatch', [
                    'kyc_verification_id' => $kyc->id,
                    'assigned_to_user_id' => $kyc->assigned_to_user_id,
                ]);
            }

            if (! in_array($kyc->status, [KycVerificationStatus::Submitted->value, KycVerificationStatus::UnderReview->value], true)) {
                throw new InvalidDomainStateTransitionException(
                    'kyc_verification',
                    $kyc->id,
                    (string) $kyc->status,
                    KycVerificationStatus::UnderReview->value,
                    'Only submitted or under-review cases can be claimed for review.',
                );
            }

            if ($kyc->assigned_to_user_id === null) {
                $kyc->assigned_to_user_id = $command->reviewerId;
                $kyc->assigned_at = now();
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

    public function reassignKycForReview(ReassignKycForReviewCommand $command): array
    {
        return DB::transaction(function () use ($command): array {
            /** @var KycVerification|null $kyc */
            $kyc = KycVerification::query()->whereKey($command->kycVerificationId)->lockForUpdate()->first();
            if ($kyc === null) {
                throw new AuthValidationFailedException('kyc_not_found', ['kyc_verification_id' => $command->kycVerificationId]);
            }

            if (! $this->isEligibleKycReviewer($command->assigneeId)) {
                throw new AuthValidationFailedException('kyc_reviewer_invalid', ['assignee_user_id' => $command->assigneeId]);
            }

            if (in_array($kyc->status, [KycVerificationStatus::Approved->value, KycVerificationStatus::Rejected->value], true)) {
                throw new InvalidDomainStateTransitionException(
                    'kyc_verification',
                    $kyc->id,
                    (string) $kyc->status,
                    KycVerificationStatus::UnderReview->value,
                    'Terminal KYC cases cannot be reassigned.',
                );
            }

            $before = $this->kycSnapshot($kyc);
            $previousAssignee = $kyc->assigned_to_user_id;

            if ((int) ($previousAssignee ?? 0) === $command->assigneeId && $kyc->status === KycVerificationStatus::UnderReview->value) {
                return $this->kycReviewResult($kyc->fresh(['seller_profile.user']), $before, $before);
            }

            $kyc->assigned_to_user_id = $command->assigneeId;
            $kyc->assigned_at = now();
            if (in_array($kyc->status, [KycVerificationStatus::Submitted->value, KycVerificationStatus::UnderReview->value], true)) {
                $kyc->status = KycVerificationStatus::UnderReview->value;
            }
            $kyc->save();

            $after = $this->kycSnapshot($kyc);

            AuditLogWriter::write(
                $command->actorId,
                'admin.kyc.reassigned',
                AuditLogWriter::TARGET_KYC_VERIFICATION,
                (int) $kyc->id,
                $before,
                $after,
                'assignment_reassigned',
                $command->correlationId,
                $command->ipAddress,
                $command->userAgent,
            );

            $this->notifyUser(
                userId: $command->assigneeId,
                templateCode: 'admin.kyc.assigned',
                payload: [
                    'title' => 'KYC reassigned',
                    'body' => 'A seller verification case has been assigned to you.',
                    'href' => route('admin.sellers.kyc.show', ['kyc' => $kyc->id]),
                    'kyc_id' => $kyc->id,
                    'seller_profile_id' => $kyc->seller_profile_id,
                ],
            );

            if ($previousAssignee !== null && (int) $previousAssignee !== $command->assigneeId) {
                $this->notifyUser(
                    userId: (int) $previousAssignee,
                    templateCode: 'admin.kyc.reassigned',
                    payload: [
                        'title' => 'KYC reassigned away',
                        'body' => 'A seller verification case has been reassigned to another reviewer.',
                        'href' => route('admin.sellers.kyc.show', ['kyc' => $kyc->id]),
                        'kyc_id' => $kyc->id,
                        'seller_profile_id' => $kyc->seller_profile_id,
                    ],
                );
            }

            $this->notifyStaff(
                templateCode: 'admin.kyc.reassigned',
                payload: [
                    'title' => 'KYC reassigned',
                    'body' => 'A seller verification case was reassigned by an admin.',
                    'href' => route('admin.sellers.kyc.show', ['kyc' => $kyc->id]),
                    'kyc_id' => $kyc->id,
                    'seller_profile_id' => $kyc->seller_profile_id,
                    'assigned_to_user_id' => $command->assigneeId,
                ],
            );

            return $this->kycReviewResult($kyc->fresh(['seller_profile.user']), $before, $after);
        });
    }

    /**
     * @return array{claimed_count: int, skipped_count: int, claimed_ids: list<int>, skipped: list<array{kyc_id: int, reason: string}>}
     */
    public function bulkClaimKycForReview(BulkClaimKycForReviewCommand $command): array
    {
        return DB::transaction(function () use ($command): array {
            $claimedIds = [];
            $skipped = [];
            $now = now();
            $ids = array_values(array_unique(array_map(static fn (int $id): int => (int) $id, $command->kycVerificationIds)));

            foreach ($ids as $id) {
                /** @var KycVerification|null $kyc */
                $kyc = KycVerification::query()->whereKey($id)->lockForUpdate()->first();
                if ($kyc === null) {
                    $skipped[] = ['kyc_id' => $id, 'reason' => 'missing'];
                    continue;
                }

                if (in_array($kyc->status, [KycVerificationStatus::Approved->value, KycVerificationStatus::Rejected->value, 'expired'], true)) {
                    $skipped[] = ['kyc_id' => $id, 'reason' => 'terminal'];
                    continue;
                }

                if ($kyc->assigned_to_user_id !== null
                    && (int) $kyc->assigned_to_user_id !== $command->reviewerId
                    && ! $this->hasElevatedKycReviewAccess($command->reviewerId)) {
                    $skipped[] = ['kyc_id' => $id, 'reason' => 'assigned'];
                    continue;
                }

                if (! in_array($kyc->status, [KycVerificationStatus::Submitted->value, KycVerificationStatus::UnderReview->value], true)) {
                    $skipped[] = ['kyc_id' => $id, 'reason' => 'state'];
                    continue;
                }

                $before = $this->kycSnapshot($kyc);
                $kyc->assigned_to_user_id = $command->reviewerId;
                $kyc->assigned_at = $kyc->assigned_at ?? $now;
                $kyc->status = KycVerificationStatus::UnderReview->value;
                $kyc->save();
                $after = $this->kycSnapshot($kyc);

                AuditLogWriter::write(
                    $command->reviewerId,
                    'admin.kyc.bulk_claimed',
                    AuditLogWriter::TARGET_KYC_VERIFICATION,
                    (int) $kyc->id,
                    $before,
                    $after,
                    'assignment',
                    $command->correlationId,
                    $command->ipAddress,
                    $command->userAgent,
                );

                $claimedIds[] = (int) $kyc->id;
            }

            return [
                'claimed_count' => count($claimedIds),
                'skipped_count' => count($skipped),
                'claimed_ids' => $claimedIds,
                'skipped' => $skipped,
            ];
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

            if ($kyc->assigned_to_user_id !== null
                && (int) $kyc->assigned_to_user_id !== $command->reviewerId
                && ! $this->hasElevatedKycReviewAccess($command->reviewerId)) {
                throw new AuthValidationFailedException('kyc_assignee_mismatch', [
                    'kyc_verification_id' => $kyc->id,
                    'assigned_to_user_id' => $kyc->assigned_to_user_id,
                ]);
            }

            $kyc->status = $targetStatus;
            if ($kyc->assigned_to_user_id === null) {
                $kyc->assigned_to_user_id = $command->reviewerId;
                $kyc->assigned_at = now();
            }
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

            $this->notifyUser(
                userId: (int) $seller->user_id,
                templateCode: $decision === 'approved' ? 'seller.kyc.approved' : 'seller.kyc.rejected',
                payload: [
                    'title' => $decision === 'approved' ? 'KYC approved' : 'KYC rejected',
                    'body' => $decision === 'approved'
                        ? 'Your seller account is now verified.'
                        : 'Your KYC case needs another review.',
                    'href' => '/seller/onboarding',
                    'seller_profile_id' => $seller->id,
                    'kyc_id' => $kyc->id,
                    'status' => $kyc->status,
                    'rejection_reason' => $kyc->rejection_reason,
                ],
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
            'assigned_to_user_id' => $kyc->assigned_to_user_id,
            'assigned_at' => $kyc->assigned_at?->toIso8601String(),
            'sla_due_at' => $kyc->sla_due_at?->toIso8601String(),
            'sla_warning_sent_at' => $kyc->sla_warning_sent_at?->toIso8601String(),
            'escalated_at' => $kyc->escalated_at?->toIso8601String(),
            'escalation_reason' => $kyc->escalation_reason,
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
            'assigned_to_user_id' => $kyc->assigned_to_user_id,
            'assigned_at' => $kyc->assigned_at?->toIso8601String(),
            'sla_due_at' => $kyc->sla_due_at?->toIso8601String(),
            'sla_warning_sent_at' => $kyc->sla_warning_sent_at?->toIso8601String(),
            'escalated_at' => $kyc->escalated_at?->toIso8601String(),
            'escalation_reason' => $kyc->escalation_reason,
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
        $profile->loadMissing('user');
        $contactEmail = $profile->contact_email ?: $profile->user?->email;
        $contactPhone = $profile->contact_phone ?: $profile->user?->phone;
        $addressParts = array_values(array_filter([
            $profile->address_line,
            $profile->city,
            $profile->region,
            $profile->postal_code,
            $profile->country,
        ], static fn ($value): bool => trim((string) ($value ?? '')) !== ''));

        return [
            'id' => $profile->id,
            'uuid' => $profile->uuid,
            'user_id' => $profile->user_id,
            'display_name' => $profile->display_name,
            'legal_name' => $profile->legal_name,
            'store_name' => $profile->display_name,
            'store_description' => $profile->legal_name,
            'country_code' => $profile->country_code,
            'default_currency' => $profile->default_currency,
            'verification_status' => (string) $profile->verification_status,
            'store_status' => (string) $profile->store_status,
            'store_logo_url' => $profile->store_logo_url,
            'banner_image_url' => $profile->banner_image_url,
            'contact_email' => $contactEmail,
            'contact_phone' => $contactPhone,
            'address_line' => $profile->address_line,
            'store_address' => implode(', ', $addressParts),
            'city' => $profile->city,
            'region' => $profile->region,
            'postal_code' => $profile->postal_code,
            'country' => $profile->country,
            'inside_dhaka_label' => $profile->inside_dhaka_label,
            'inside_dhaka_fee' => $profile->inside_dhaka_fee,
            'outside_dhaka_label' => $profile->outside_dhaka_label,
            'outside_dhaka_fee' => $profile->outside_dhaka_fee,
            'cash_on_delivery_enabled' => $profile->cash_on_delivery_enabled,
            'processing_time_label' => $profile->processing_time_label,
            'created_at' => $profile->created_at?->toIso8601String(),
            'updated_at' => $profile->updated_at?->toIso8601String(),
        ];
    }

    private function sellerProfileForUserOrFail(int $userId): SellerProfile
    {
        $profile = SellerProfile::query()->where('user_id', $userId)->first();
        if ($profile === null) {
            throw new AuthValidationFailedException('seller_profile_not_found', ['user_id' => $userId]);
        }

        return $profile;
    }

    /**
     * @return array<string, mixed>
     */
    private function sellerShippingSettingsToArray(SellerProfile $profile): array
    {
        $availableMethods = ShippingMethod::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(static fn (ShippingMethod $method): array => [
                'id' => $method->id,
                'code' => $method->code,
                'name' => $method->name,
                'suggested_fee' => (float) $method->suggested_fee,
                'processing_time_label' => $method->processing_time_label,
                'sort_order' => (int) $method->sort_order,
            ])
            ->values()
            ->all();
        $selectedMethods = $profile->shippingMethods
            ->filter(static fn (SellerShippingMethod $item): bool => (bool) $item->is_enabled && $item->shippingMethod !== null)
            ->sortBy('sort_order')
            ->map(static fn (SellerShippingMethod $item): array => [
                'shipping_method_id' => $item->shipping_method_id,
                'method_code' => $item->shippingMethod?->code,
                'method_name' => $item->shippingMethod?->name,
                'suggested_fee' => (float) ($item->shippingMethod?->suggested_fee ?? 0),
                'price' => (float) $item->price,
                'processing_time_label' => $item->processing_time_label,
                'is_enabled' => (bool) $item->is_enabled,
                'sort_order' => (int) $item->sort_order,
            ])
            ->values()
            ->all();
        $isConfigured = $profile->inside_dhaka_label !== null
            || $profile->inside_dhaka_fee !== null
            || $profile->outside_dhaka_label !== null
            || $profile->outside_dhaka_fee !== null
            || $profile->cash_on_delivery_enabled !== null
            || $profile->processing_time_label !== null
            || $selectedMethods !== [];

        return [
            'is_configured' => $isConfigured,
            'available_methods' => $availableMethods,
            'shipping_methods' => $selectedMethods,
            'processing_time_options' => $this->processingTimeOptions(),
            'inside_dhaka_label' => $profile->inside_dhaka_label,
            'inside_dhaka_fee' => $profile->inside_dhaka_fee,
            'outside_dhaka_label' => $profile->outside_dhaka_label,
            'outside_dhaka_fee' => $profile->outside_dhaka_fee,
            'cash_on_delivery_enabled' => $profile->cash_on_delivery_enabled,
            'processing_time_label' => $profile->processing_time_label,
        ];
    }

    private function shippingMethodIdForName(string $name): ?int
    {
        $trimmed = trim($name);
        if ($trimmed === '') {
            return null;
        }
        $code = strtolower(preg_replace('/[^a-z0-9]+/i', '_', $trimmed) ?? '');
        $code = trim($code, '_');
        $method = ShippingMethod::query()
            ->where('name', $trimmed)
            ->orWhere('code', $code)
            ->first();

        return $method !== null ? (int) $method->id : null;
    }

    /**
     * @return list<string>
     */
    private function processingTimeOptions(): array
    {
        return ['Instant', 'Same day', '1-2 Business Days', '3-5 Business Days', '5-7 Business Days'];
    }

    /**
     * @return array<string, mixed>
     */
    private function payoutAccountToArray(PayoutAccount $account): array
    {
        $details = json_decode((string) $account->account_ref_token, true);
        if (! is_array($details)) {
            $details = [];
        }
        $accountNumber = (string) ($details['account_number'] ?? $account->account_ref_token ?? '');
        $bankName = (string) ($details['bank_name'] ?? '');
        $methodType = (string) ($details['method_type'] ?? '');
        if (! in_array($methodType, ['bkash', 'nagad', 'bank_transfer'], true)) {
            $methodType = match ((string) $account->account_type) {
                'bank' => 'bank_transfer',
                'mobile_money' => in_array((string) $account->provider, ['bkash', 'nagad'], true)
                    ? (string) $account->provider
                    : 'bkash',
                default => 'bank_transfer',
            };
        }

        return [
            'id' => (int) $account->id,
            'method_type' => $methodType,
            'type' => $methodType,
            'account_name' => (string) ($details['account_name'] ?? ''),
            'account_number_masked' => $this->maskAccountRef($accountNumber),
            'provider_name' => $account->provider,
            'bank_name' => $bankName !== '' ? $bankName : $account->provider,
            'branch_name' => (string) ($details['branch_name'] ?? ''),
            'routing_number' => (string) ($details['routing_number'] ?? ''),
            'account_type_label' => (string) ($details['account_type_label'] ?? ''),
            'is_default' => (bool) $account->is_default,
            'is_active' => (string) $account->status === 'active',
            'status' => (string) $account->status,
        ];
    }

    private function stringOrDefault(mixed $value, string $default, int $max): string
    {
        $text = trim((string) ($value ?? ''));
        if ($text === '') {
            return $default;
        }

        return substr($text, 0, $max);
    }

    private function moneyOrDefault(mixed $value, float $default): float
    {
        $amount = is_numeric($value) ? (float) $value : $default;

        return max(0.0, round($amount, 2));
    }

    private function maskAccountRef(string $value): string
    {
        $clean = trim($value);
        if ($clean === '') {
            return '';
        }
        $length = strlen($clean);
        if ($length <= 4) {
            return str_repeat('*', $length);
        }

        return str_repeat('*', max(0, $length - 4)).substr($clean, -4);
    }

    /**
     * @return array<string, mixed>
     */
    private function sellerProfileToArrayWithKyc(SellerProfile $profile, ?KycVerification $latestKyc = null): array
    {
        $latestKyc ??= KycVerification::query()
            ->where('seller_profile_id', $profile->id)
            ->orderByDesc('id')
            ->with('kycDocuments')
            ->first();

        $data = $this->sellerProfileToArray($profile);
        $data['latest_kyc'] = $latestKyc === null ? null : [
            'id' => $latestKyc->id,
            'uuid' => $latestKyc->uuid,
            'status' => $latestKyc->status,
            'provider_ref' => $latestKyc->provider_ref,
            'assigned_to_user_id' => $latestKyc->assigned_to_user_id,
            'assigned_at' => $latestKyc->assigned_at?->toIso8601String(),
            'submitted_at' => $latestKyc->submitted_at?->toIso8601String(),
            'reviewed_at' => $latestKyc->reviewed_at?->toIso8601String(),
            'rejection_reason' => $latestKyc->rejection_reason,
            'documents' => $latestKyc->kycDocuments->map(static function (KycDocument $doc): array {
                return [
                    'id' => $doc->id,
                    'doc_type' => $doc->doc_type,
                    'storage_path' => $doc->storage_path,
                    'checksum_sha256' => $doc->checksum_sha256,
                    'status' => $doc->status,
                ];
            })->values()->all(),
        ];
        $data['latest_kyc_status'] = $latestKyc?->status;
        $data['latest_kyc_submitted_at'] = $latestKyc?->submitted_at?->toIso8601String();
        $data['latest_kyc_assigned_to_user_id'] = $latestKyc?->assigned_to_user_id;
        $data['latest_kyc_assigned_at'] = $latestKyc?->assigned_at?->toIso8601String();
        $data['latest_kyc_sla_due_at'] = $latestKyc?->sla_due_at?->toIso8601String();
        $data['latest_kyc_sla_warning_sent_at'] = $latestKyc?->sla_warning_sent_at?->toIso8601String();
        $data['latest_kyc_escalated_at'] = $latestKyc?->escalated_at?->toIso8601String();
        $data['latest_kyc_escalation_reason'] = $latestKyc?->escalation_reason;

        return $data;
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
            'details' => is_array($m->details_json ?? null) ? $m->details_json : [],
            'is_default' => (bool) $m->is_default,
            'created_at' => $m->created_at?->toIso8601String(),
        ];
    }

    /**
     * @param  array{kind?: string, label?: string, subtitle?: string, details?: array<string, mixed>, is_default?: bool}  $payload
     * @return array<string, mixed>
     */
    private function persistBuyerPaymentMethod(int $userId, ?UserPaymentMethod $method, array $payload): array
    {
        $kind = strtolower(trim((string) ($payload['kind'] ?? $method?->kind ?? 'card')));
        $label = trim((string) ($payload['label'] ?? $method?->label ?? ''));
        $subtitle = trim((string) ($payload['subtitle'] ?? $method?->subtitle ?? ''));
        $details = is_array($payload['details'] ?? null)
            ? $this->normalizePaymentMethodDetails($kind, $payload['details'])
            : ($method?->details_json ?? []);

        if ($label === '') {
            throw new AuthValidationFailedException('validation_failed', ['label' => 'required']);
        }

        if (! in_array($kind, ['card', 'bkash', 'nagad', 'bank'], true)) {
            $kind = 'card';
        }

        $isDefault = array_key_exists('is_default', $payload)
            ? (bool) ($payload['is_default'] ?? false)
            : (bool) ($method?->is_default ?? false);

        $exists = UserPaymentMethod::query()->where('user_id', $userId)->exists();
        if (! $exists) {
            $isDefault = true;
        }

        if ($isDefault) {
            UserPaymentMethod::query()
                ->where('user_id', $userId)
                ->when($method !== null, static fn ($q) => $q->where('id', '!=', $method->id))
                ->update(['is_default' => false]);
        }

        if ($method === null) {
            $method = UserPaymentMethod::query()->create([
                'uuid' => (string) Str::uuid(),
                'user_id' => $userId,
                'kind' => $kind,
                'label' => $label,
                'subtitle' => $subtitle !== '' ? $subtitle : null,
                'details_json' => $details === [] ? null : $details,
                'is_default' => $isDefault,
            ]);
        } else {
            $method->kind = $kind;
            $method->label = $label;
            $method->subtitle = $subtitle !== '' ? $subtitle : null;
            $method->details_json = $details === [] ? null : $details;
            $method->is_default = $isDefault;
            $method->save();
        }

        if (! $isDefault) {
            $hasDefault = UserPaymentMethod::query()
                ->where('user_id', $userId)
                ->where('is_default', true)
                ->exists();
            if (! $hasDefault) {
                $method->is_default = true;
                $method->save();
            }
        }

        return $this->paymentMethodToArray($method->fresh());
    }

    /**
     * @param array<string, mixed> $details
     * @return array<string, mixed>
     */
    private function normalizePaymentMethodDetails(string $kind, array $details): array
    {
        $normalized = [];
        foreach ($details as $key => $value) {
            $normalized[(string) $key] = trim((string) $value);
        }

        return match ($kind) {
            'card' => array_filter([
                'cardholder_name' => $normalized['cardholder_name'] ?? '',
                'card_brand' => $normalized['card_brand'] ?? '',
                'last4' => $normalized['last4'] ?? '',
                'note' => $normalized['note'] ?? '',
            ], static fn (string $value): bool => $value !== ''),
            'bkash', 'nagad' => array_filter([
                'account_name' => $normalized['account_name'] ?? '',
                'mobile_number' => $normalized['mobile_number'] ?? '',
                'note' => $normalized['note'] ?? '',
            ], static fn (string $value): bool => $value !== ''),
            'bank' => array_filter([
                'account_name' => $normalized['account_name'] ?? '',
                'bank_name' => $normalized['bank_name'] ?? '',
                'account_number' => $normalized['account_number'] ?? '',
                'branch' => $normalized['branch'] ?? '',
                'routing_number' => $normalized['routing_number'] ?? '',
                'note' => $normalized['note'] ?? '',
            ], static fn (string $value): bool => $value !== ''),
            default => array_filter($normalized, static fn (string $value): bool => $value !== ''),
        };
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function notifyUser(int $userId, string $templateCode, array $payload): void
    {
        $notification = Notification::query()->create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $userId,
            'channel' => 'in_app',
            'template_code' => $templateCode,
            'payload_json' => $payload,
            'status' => 'queued',
            'sent_at' => now(),
        ]);

        UserNotificationCreated::dispatch(
            $userId,
            $this->notificationToArray($notification),
            Notification::query()->where('user_id', $userId)->whereNull('read_at')->count(),
        );

        app(PushNotificationService::class)->sendToUser($userId, $this->notificationToArray($notification));
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function notifyStaff(string $templateCode, array $payload): void
    {
        $staffIds = User::query()
            ->whereHas('roles', static function ($q): void {
                $q->whereIn('roles.code', [
                    RoleCodes::SuperAdmin,
                    RoleCodes::Admin,
                    RoleCodes::Adjudicator,
                    RoleCodes::KycReviewer,
                ]);
            })
            ->pluck('id')
            ->all();

        foreach ($staffIds as $staffId) {
            $this->notifyUser((int) $staffId, $templateCode, $payload);
        }
    }

    private function resolveKycAssigneeId(): ?int
    {
        $policy = AdminEscalationPolicy::query()
            ->where('queue_code', 'seller_kyc')
            ->where('is_enabled', true)
            ->first();

        if ($policy !== null && $policy->auto_assign_on_call && $policy->on_call_role_code !== null && $policy->on_call_role_code !== '') {
            $assigned = $this->resolveOnCallAssigneeId($policy->on_call_role_code);
            if ($assigned !== null) {
                return $assigned;
            }
        }

        return $this->leastLoadedKycReviewerId();
    }

    private function resolveOnCallAssigneeId(string $roleCode): ?int
    {
        $weekday = (int) now()->dayOfWeek;
        $hour = (int) now()->hour;

        $rotation = AdminOnCallRotation::query()
            ->where('role_code', $roleCode)
            ->where('is_active', true)
            ->where('weekday', $weekday)
            ->where('start_hour', '<=', $hour)
            ->where('end_hour', '>=', $hour)
            ->orderBy('priority')
            ->first();

        return $rotation?->user_id;
    }

    private function leastLoadedKycReviewerId(): ?int
    {
        $row = User::query()
            ->select(['users.id'])
            ->join('user_roles', 'user_roles.user_id', '=', 'users.id')
            ->join('roles', 'roles.id', '=', 'user_roles.role_id')
            ->leftJoin('kyc_verifications as kv', static function ($join): void {
                $join->on('kv.assigned_to_user_id', '=', 'users.id')
                    ->whereIn('kv.status', [
                        KycVerificationStatus::Submitted->value,
                        KycVerificationStatus::UnderReview->value,
                    ]);
            })
            ->whereNull('users.deleted_at')
            ->whereIn('roles.code', [RoleCodes::KycReviewer, RoleCodes::Admin, RoleCodes::SuperAdmin])
            ->groupBy('users.id')
            ->selectRaw('COUNT(DISTINCT kv.id) as open_cases')
            ->orderBy('open_cases')
            ->orderBy('users.id')
            ->first();

        return $row === null ? null : (int) $row->id;
    }

    private function hasElevatedKycReviewAccess(int $userId): bool
    {
        $user = User::query()->with('roles')->find($userId);
        if ($user === null) {
            return false;
        }

        return $user->hasRoleCode(RoleCodes::SuperAdmin)
            || $user->hasRoleCode(RoleCodes::Admin)
            || $user->hasRoleCode(RoleCodes::Adjudicator);
    }

    private function isEligibleKycReviewer(int $userId): bool
    {
        $user = User::query()->with('roles')->find($userId);
        if ($user === null) {
            return false;
        }

        return $user->hasRoleCode(RoleCodes::SuperAdmin)
            || $user->hasRoleCode(RoleCodes::Admin)
            || $user->hasRoleCode(RoleCodes::Adjudicator)
            || $user->hasRoleCode(RoleCodes::KycReviewer);
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

    /**
     * @return array<string, mixed>
     */
    private function sellerReviewToArray(Review $r): array
    {
        $orderNo = $r->order_item?->order?->order_number ?? '—';
        $createdAt = $r->created_at;

        return [
            'id' => (int) $r->id,
            'buyer_name' => (string) ($r->buyer?->display_name ?? $r->buyer?->email ?? 'Buyer'),
            'order_no' => (string) $orderNo,
            'order_number' => (string) $orderNo,
            'product_id' => (int) $r->product_id,
            'product_name' => (string) ($r->product?->title ?? $r->order_item?->title_snapshot ?? 'Product'),
            'rating' => (int) $r->rating,
            'comment' => (string) ($r->comment ?? ''),
            'status' => (string) $r->status,
            'is_verified_buyer' => true,
            'photo_urls' => [],
            'created_at' => $createdAt?->toIso8601String(),
            'created_at_label' => $createdAt?->format('d M Y') ?? '',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function notificationToArray(\App\Models\Notification $notification): array
    {
        $payload = is_array($notification->payload_json) ? $notification->payload_json : [];

        return [
            'id' => (int) $notification->id,
            'uuid' => $notification->uuid,
            'channel' => (string) $notification->channel,
            'template_code' => $notification->template_code,
            'title' => (string) ($payload['title'] ?? $notification->template_code ?? 'Notification'),
            'body' => (string) ($payload['body'] ?? ''),
            'href' => (string) ($payload['href'] ?? ''),
            'payload' => $payload,
            'is_read' => $notification->read_at !== null,
            'read' => $notification->read_at !== null,
            'read_at' => $notification->read_at?->toIso8601String(),
            'created_at' => $notification->created_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function notificationPreferenceToArray(UserNotificationPreference $pref): array
    {
        return [
            'in_app_enabled' => (bool) $pref->in_app_enabled,
            'email_enabled' => (bool) $pref->email_enabled,
            'order_updates_enabled' => (bool) $pref->order_updates_enabled,
            'promotion_enabled' => (bool) $pref->promotion_enabled,
            'updated_at' => $pref->updated_at?->toIso8601String(),
        ];
    }
}
