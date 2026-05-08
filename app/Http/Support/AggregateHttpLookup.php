<?php

declare(strict_types=1);

namespace App\Http\Support;

use App\Domain\Exceptions\DisputeResolutionConflictException;
use App\Domain\Exceptions\OrderValidationFailedException;
use App\Domain\Exceptions\ProductValidationFailedException;
use App\Domain\Exceptions\WalletNotFoundException;
use App\Domain\Exceptions\WithdrawalValidationFailedException;
use App\Models\DisputeCase;
use App\Models\Order;
use App\Models\Product;
use App\Models\SellerProfile;
use App\Models\Wallet;
use App\Models\WithdrawalRequest;

/**
 * Resolves aggregates for HTTP actions and throws domain exceptions mapped by {@see ExceptionToHttpMapper}.
 */
final class AggregateHttpLookup
{
    public static function order(int $orderId): Order
    {
        $order = Order::query()->find($orderId);
        if ($order === null) {
            throw new OrderValidationFailedException($orderId, 'order_not_found', ['order_id' => $orderId]);
        }

        return $order;
    }

    public static function product(int $productId): Product
    {
        $product = Product::query()->find($productId);
        if ($product === null) {
            throw new ProductValidationFailedException($productId, 'product_not_found', ['product_id' => $productId]);
        }

        return $product;
    }

    public static function disputeCase(int $disputeCaseId): DisputeCase
    {
        $case = DisputeCase::query()->find($disputeCaseId);
        if ($case === null) {
            throw new DisputeResolutionConflictException($disputeCaseId, 'dispute_case_not_found');
        }

        return $case;
    }

    public static function withdrawalRequest(int $withdrawalRequestId): WithdrawalRequest
    {
        $wr = WithdrawalRequest::query()->find($withdrawalRequestId);
        if ($wr === null) {
            throw new WithdrawalValidationFailedException(
                $withdrawalRequestId,
                'withdrawal_request_not_found',
                ['withdrawal_request_id' => $withdrawalRequestId],
            );
        }

        return $wr;
    }

    public static function sellerProfile(int $sellerProfileId): SellerProfile
    {
        $profile = SellerProfile::query()->find($sellerProfileId);
        if ($profile === null) {
            throw new WithdrawalValidationFailedException(
                null,
                'seller_profile_not_found',
                ['seller_profile_id' => $sellerProfileId],
            );
        }

        return $profile;
    }

    public static function wallet(int $walletId): Wallet
    {
        $wallet = Wallet::query()->find($walletId);
        if ($wallet === null) {
            throw new WalletNotFoundException($walletId);
        }

        return $wallet;
    }
}
