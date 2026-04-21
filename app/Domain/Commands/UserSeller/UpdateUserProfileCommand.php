<?php

namespace App\Domain\Commands\UserSeller;

use App\Domain\Value\UserProfilePatch;

/**
 * Input contract for {@see \App\Services\UserSeller\UserSellerService::updateProfile}.
 */
final readonly class UpdateUserProfileCommand
{
    public function __construct(
        public int $userId,
        public UserProfilePatch $patch,
    ) {
    }
}
