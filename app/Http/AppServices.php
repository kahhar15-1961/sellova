<?php

declare(strict_types=1);

namespace App\Http;

use App\Auth\DomainGate;
use App\Http\Auth\AuthenticationRequiredException;
use App\Models\User;
use App\Services\Auth\AuthService;
use App\Services\Category\CategoryService;
use App\Services\Dispute\DisputeService;
use App\Services\Order\OrderService;
use App\Services\Promotion\PromotionService;
use App\Services\Product\ProductService;
use App\Services\Returns\ReturnService;
use App\Services\WalletLedger\WalletLedgerService;
use App\Services\WalletTopUp\WalletTopUpRequestService;
use App\Services\UserSeller\UserSellerService;
use App\Services\Withdrawal\WithdrawalService;
use Symfony\Component\HttpFoundation\Request;

/**
 * Service locator for legacy JSON API controllers (mobile).
 * Not to be confused with {@see \Illuminate\Foundation\Application}.
 */
final class AppServices
{
    private ?OrderService $orderService = null;

    private ?WithdrawalService $withdrawalService = null;

    private ?DisputeService $disputeService = null;

    private ?AuthService $authService = null;

    private ?UserSellerService $userSellerService = null;

    private ?ProductService $productService = null;

    private ?CategoryService $categoryService = null;

    private ?ReturnService $returnService = null;

    private ?PromotionService $promotionService = null;

    private ?WalletLedgerService $walletLedgerService = null;

    private ?WalletTopUpRequestService $walletTopUpRequestService = null;

    private ?DomainGate $domainGate = null;

    public function orderService(): OrderService
    {
        return $this->orderService ??= new OrderService();
    }

    public function withdrawalService(): WithdrawalService
    {
        return $this->withdrawalService ??= new WithdrawalService();
    }

    public function disputeService(): DisputeService
    {
        return $this->disputeService ??= new DisputeService();
    }

    public function authService(): AuthService
    {
        return $this->authService ??= new AuthService();
    }

    public function userSellerService(): UserSellerService
    {
        return $this->userSellerService ??= app(UserSellerService::class);
    }

    public function productService(): ProductService
    {
        return $this->productService ??= new ProductService();
    }

    public function categoryService(): CategoryService
    {
        return $this->categoryService ??= new CategoryService();
    }

    public function returnService(): ReturnService
    {
        return $this->returnService ??= new ReturnService();
    }

    public function promotionService(): PromotionService
    {
        return $this->promotionService ??= new PromotionService();
    }

    public function walletLedgerService(): WalletLedgerService
    {
        return $this->walletLedgerService ??= new WalletLedgerService();
    }

    public function walletTopUpRequestService(): WalletTopUpRequestService
    {
        return $this->walletTopUpRequestService ??= new WalletTopUpRequestService();
    }

    public function domainGate(): DomainGate
    {
        return $this->domainGate ??= new DomainGate();
    }

    public function requireActor(Request $request): User
    {
        $actor = $request->attributes->get('actor');
        if (! $actor instanceof User) {
            throw new AuthenticationRequiredException();
        }

        return $actor;
    }

    public function optionalActor(Request $request): ?User
    {
        $actor = $request->attributes->get('actor');

        return $actor instanceof User ? $actor : null;
    }
}
