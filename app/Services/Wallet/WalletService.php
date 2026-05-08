<?php

namespace App\Services\Wallet;

use App\Domain\Commands\WalletLedger\ComputeWalletBalancesCommand;
use App\Domain\Commands\WalletLedger\CreateWalletIfMissingCommand;
use App\Domain\Enums\WalletType;
use App\Models\Wallet;
use App\Services\WalletLedger\WalletLedgerService;

final class WalletService
{
    public function __construct(private readonly WalletLedgerService $ledger = new WalletLedgerService())
    {
    }

    /**
     * Server-side, user-isolated balance resolution. Frontend supplied balances are never accepted.
     *
     * @return array<string, mixed>
     */
    public function balancesForUser(int $userId, WalletType $walletType, string $currency): array
    {
        $this->ledger->createWalletIfMissing(new CreateWalletIfMissingCommand(
            userId: $userId,
            walletType: $walletType,
            currency: strtoupper($currency),
        ));

        $wallet = Wallet::query()
            ->where('user_id', $userId)
            ->where('wallet_type', $walletType->value)
            ->where('currency', strtoupper($currency))
            ->firstOrFail();

        return $this->ledger->computeWalletBalances(new ComputeWalletBalancesCommand((int) $wallet->id));
    }
}

