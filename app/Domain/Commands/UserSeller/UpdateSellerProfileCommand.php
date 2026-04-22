<?php

declare(strict_types=1);

namespace App\Domain\Commands\UserSeller;

/**
 * Input contract for {@see \App\Services\UserSeller\UserSellerService::updateSellerProfile}.
 */
final readonly class UpdateSellerProfileCommand
{
    public function __construct(
        public int $ownerUserId,
        public ?string $displayName = null,
        public ?string $legalName = null,
    ) {
    }
}
