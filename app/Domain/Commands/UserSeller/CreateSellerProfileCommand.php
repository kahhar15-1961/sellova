<?php

namespace App\Domain\Commands\UserSeller;

use App\Domain\Value\SellerProfileDraft;

/**
 * Input contract for {@see \App\Services\UserSeller\UserSellerService::createSellerProfile}.
 */
final readonly class CreateSellerProfileCommand
{
    public function __construct(
        public int $userId,
        public SellerProfileDraft $draft,
    ) {
    }
}
