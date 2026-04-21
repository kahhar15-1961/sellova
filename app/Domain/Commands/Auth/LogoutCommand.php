<?php

namespace App\Domain\Commands\Auth;

/**
 * Input contract for {@see \App\Services\Auth\AuthService::logout}.
 */
final readonly class LogoutCommand
{
    public function __construct(
        public int $userId,
    ) {
    }
}
