<?php

namespace App\Domain\Commands\Auth;

/**
 * Input contract for {@see \App\Services\Auth\AuthService::refreshToken}.
 */
final readonly class RefreshTokenCommand
{
    public function __construct(
        public string $refreshToken,
    ) {
    }
}
