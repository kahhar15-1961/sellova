<?php

namespace App\Services\Auth;

use App\Domain\Commands\Auth\LoginCommand;
use App\Domain\Commands\Auth\LogoutCommand;
use App\Domain\Commands\Auth\RefreshTokenCommand;
use App\Domain\Commands\Auth\RegisterBuyerCommand;
use App\Domain\Commands\Auth\RegisterSellerCommand;

class AuthService
{
    public function registerBuyer(RegisterBuyerCommand $command): array
    {
        throw new \LogicException('Not implemented.');
    }

    public function registerSeller(RegisterSellerCommand $command): array
    {
        throw new \LogicException('Not implemented.');
    }

    public function login(LoginCommand $command): array
    {
        throw new \LogicException('Not implemented.');
    }

    public function logout(LogoutCommand $command): void
    {
        throw new \LogicException('Not implemented.');
    }

    public function refreshToken(RefreshTokenCommand $command): array
    {
        throw new \LogicException('Not implemented.');
    }
}
