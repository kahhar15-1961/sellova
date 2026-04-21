<?php

namespace App\Services\UserSeller;

use App\Domain\Commands\UserSeller\CreateSellerProfileCommand;
use App\Domain\Commands\UserSeller\ReviewKycCommand;
use App\Domain\Commands\UserSeller\SubmitKycCommand;
use App\Domain\Commands\UserSeller\UpdateUserProfileCommand;

class UserSellerService
{
    public function updateProfile(UpdateUserProfileCommand $command): array
    {
        throw new \LogicException('Not implemented.');
    }

    public function createSellerProfile(CreateSellerProfileCommand $command): array
    {
        throw new \LogicException('Not implemented.');
    }

    public function submitKyc(SubmitKycCommand $command): array
    {
        throw new \LogicException('Not implemented.');
    }

    public function reviewKyc(ReviewKycCommand $command): array
    {
        throw new \LogicException('Not implemented.');
    }
}
