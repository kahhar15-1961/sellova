<?php

namespace App\Domain\Value;

/**
 * Partial user profile update (non-null fields are applied).
 */
final readonly class UserProfilePatch
{
    public function __construct(
        public ?string $displayName = null,
        public ?string $email = null,
        public ?string $phone = null,
        public ?string $passwordPlain = null,
    ) {
    }
}
