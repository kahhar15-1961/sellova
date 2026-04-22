<?php

declare(strict_types=1);

namespace App\Http\Requests\V1;

use App\Http\Validation\AbstractValidatedRequest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Constraints\Collection;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Optional;
use Symfony\Component\Validator\Constraints\Sequentially;
use Symfony\Component\Validator\Constraints\Type;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

final class LoginRequest extends AbstractValidatedRequest
{
    /**
     * @return array{email: string, phone: string, password: string, device_name: string}
     */
    public static function credentials(Request $request): array
    {
        $payload = self::validate($request);

        return [
            'email' => trim((string) ($payload['email'] ?? '')),
            'phone' => trim((string) ($payload['phone'] ?? '')),
            'password' => (string) $payload['password'],
            'device_name' => trim((string) ($payload['device_name'] ?? '')),
        ];
    }

    protected static function constraint(): Constraint
    {
        return new Sequentially([
            new Collection([
                'fields' => [
                    'email' => new Optional([new Type('string'), new Email()]),
                    'phone' => new Optional([new Type('string'), new Length(max: 32)]),
                    'password' => [new NotBlank(), new Type('string'), new Length(min: 1, max: 1024)],
                    'device_name' => new Optional([new Type('string'), new Length(max: 128)]),
                ],
                'allowMissingFields' => true,
                'allowExtraFields' => false,
            ]),
            new Callback(static function (mixed $payload, ExecutionContextInterface $context): void {
                if (! is_array($payload)) {
                    return;
                }
                $email = trim((string) ($payload['email'] ?? ''));
                $phone = trim((string) ($payload['phone'] ?? ''));
                if ($email === '' && $phone === '') {
                    $context->buildViolation('Either email or phone is required.')
                        ->atPath('[email]')
                        ->addViolation();
                }
            }),
        ]);
    }
}
