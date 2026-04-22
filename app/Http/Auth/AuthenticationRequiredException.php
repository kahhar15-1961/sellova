<?php

declare(strict_types=1);

namespace App\Http\Auth;

final class AuthenticationRequiredException extends \RuntimeException
{
    public function __construct(string $message = 'Authentication required.')
    {
        parent::__construct($message);
    }
}
