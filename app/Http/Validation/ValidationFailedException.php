<?php

declare(strict_types=1);

namespace App\Http\Validation;

use Symfony\Component\Validator\ConstraintViolationListInterface;

/**
 * Carries Symfony validator violations for {@see ExceptionToHttpMapper}.
 */
final class ValidationFailedException extends \RuntimeException
{
    /**
     * @param  list<array{field: string, message: string}>  $errors
     */
    public function __construct(
        public readonly array $errors,
        string $message = 'Validation failed.',
    ) {
        parent::__construct($message);
    }

    public static function fromViolations(ConstraintViolationListInterface $violations): self
    {
        $errors = [];
        foreach ($violations as $v) {
            $path = $v->getPropertyPath();
            $errors[] = [
                'field' => $path === '' ? '_' : $path,
                'message' => (string) $v->getMessage(),
            ];
        }

        return new self($errors);
    }
}
