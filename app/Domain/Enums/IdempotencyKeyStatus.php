<?php

namespace App\Domain\Enums;

/**
 * Idempotency record lifecycle (matches `idempotency_keys.status` ENUM).
 */
enum IdempotencyKeyStatus: string
{
    case Started = 'started';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
}
