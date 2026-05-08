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
        public ?string $storeLogoUrl = null,
        public ?string $bannerImageUrl = null,
        public ?string $contactEmail = null,
        public ?string $contactPhone = null,
        public ?string $addressLine = null,
        public ?string $city = null,
        public ?string $region = null,
        public ?string $postalCode = null,
        public ?string $country = null,
    ) {
    }
}
