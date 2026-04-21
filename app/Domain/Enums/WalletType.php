<?php

namespace App\Domain\Enums;

/**
 * Wallet role (matches `wallets.wallet_type` ENUM).
 */
enum WalletType: string
{
    case Buyer = 'buyer';
    case Seller = 'seller';
    case Platform = 'platform';
}
