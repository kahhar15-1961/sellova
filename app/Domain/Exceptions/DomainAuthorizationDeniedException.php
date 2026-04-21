<?php

namespace App\Domain\Exceptions;

/**
 * Caller is not permitted to perform the requested domain action.
 */
final class DomainAuthorizationDeniedException extends DomainException
{
    public function __construct(
        public readonly string $action,
        public readonly ?int $actorUserId = null,
        string $message = '',
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        $default = $actorUserId !== null
            ? sprintf('Authorization denied for action "%s" (user %d).', $action, $actorUserId)
            : sprintf('Authorization denied for action "%s".', $action);
        parent::__construct($message !== '' ? $message : $default, $code, $previous);
    }
}
