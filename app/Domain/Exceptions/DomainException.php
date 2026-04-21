<?php

namespace App\Domain\Exceptions;

/**
 * Base for all domain-level failures (non-validation framework).
 */
abstract class DomainException extends \RuntimeException
{
}
