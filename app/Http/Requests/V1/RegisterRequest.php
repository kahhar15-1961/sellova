<?php

declare(strict_types=1);

namespace App\Http\Requests\V1;

use App\Http\Validation\AbstractValidatedRequest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\Collection;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Optional;
use Symfony\Component\Validator\Constraints\Sequentially;
use Symfony\Component\Validator\Constraints\Type;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

final class RegisterRequest extends AbstractValidatedRequest
{
    /**
     * @return array{
     *     account_type: string,
     *     email: string,
     *     phone: string|null,
     *     password: string,
     *     display_name: string,
     *     legal_name: string|null,
     *     country_code: string,
     *     default_currency: string
     * }
     */
    public static function payload(Request $request): array
    {
        $p = self::validate($request);
        $phone = isset($p['phone']) ? trim((string) $p['phone']) : null;
        if ($phone === '') {
            $phone = null;
        }
        $legal = isset($p['legal_name']) ? trim((string) $p['legal_name']) : null;
        if ($legal === '') {
            $legal = null;
        }

        return [
            'account_type' => (string) $p['account_type'],
            'email' => trim((string) $p['email']),
            'phone' => $phone,
            'password' => (string) $p['password'],
            'display_name' => trim((string) $p['display_name']),
            'legal_name' => $legal,
            'country_code' => strtoupper(trim((string) $p['country_code'])),
            'default_currency' => strtoupper(trim((string) $p['default_currency'])),
        ];
    }

    protected static function constraint(): Constraint
    {
        return new Sequentially([
            new Collection([
                'fields' => [
                    'account_type' => [new NotBlank(), new Type('string'), new Choice(['buyer', 'seller'])],
                    'email' => [new NotBlank(), new Type('string'), new Email()],
                    'phone' => new Optional([new Type('string'), new Length(max: 32)]),
                    'password' => [new NotBlank(), new Type('string'), new Length(min: 8, max: 1024)],
                    'display_name' => [new NotBlank(), new Type('string'), new Length(min: 1, max: 191)],
                    'legal_name' => new Optional([new Type('string'), new Length(max: 255)]),
                    'country_code' => [new NotBlank(), new Type('string'), new Length(exactly: 2)],
                    'default_currency' => [new NotBlank(), new Type('string'), new Length(exactly: 3)],
                ],
                'allowMissingFields' => true,
                'allowExtraFields' => false,
            ]),
            new Callback(static function (mixed $payload, ExecutionContextInterface $context): void {
                if (! is_array($payload)) {
                    return;
                }
                if (($payload['account_type'] ?? '') !== 'seller') {
                    return;
                }
                if (trim((string) ($payload['legal_name'] ?? '')) === '') {
                    $context->buildViolation('This value should not be blank.')
                        ->atPath('[legal_name]')
                        ->addViolation();
                }
            }),
        ]);
    }
}
