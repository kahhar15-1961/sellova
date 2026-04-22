<?php

declare(strict_types=1);

namespace App\Http;

use App\Auth\DomainGate;
use App\Http\Auth\AuthenticationRequiredException;
use App\Models\User;
use App\Services\Dispute\DisputeService;
use App\Services\Order\OrderService;
use App\Services\Withdrawal\WithdrawalService;
use Symfony\Component\HttpFoundation\Request;

final class Application
{
    private ?OrderService $orderService = null;

    private ?WithdrawalService $withdrawalService = null;

    private ?DisputeService $disputeService = null;

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
