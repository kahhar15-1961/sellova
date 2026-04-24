<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;

/**
 * Session-auth identity for the Inertia admin panel. Same {@code users} row as {@see User};
 * kept separate so the mobile JSON API stack is not coupled to Laravel's session guard.
 */
class StaffUser extends User implements AuthenticatableContract
{
    use Authenticatable;

    public function getAuthPassword(): string
    {
        return (string) $this->password_hash;
    }
}
