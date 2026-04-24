<?php

declare(strict_types=1);

namespace App\Services\Auth;

use Firebase\JWT\JWT;
use Firebase\JWT\JWK;

/**
 * Verifies a Sign in with Apple identity token (JWT) against Apple's JWKS.
 */
final class AppleIdTokenVerifier
{
    private const JWKS_URI = 'https://appleid.apple.com/auth/keys';

    /**
     * @return array{sub: string, email: ?string, email_verified: bool}
     */
    public static function verify(string $jwt, string $expectedAudience): array
    {
        $expectedAudience = trim($expectedAudience);
        if ($expectedAudience === '') {
            throw new \InvalidArgumentException('Apple client id (audience) is not configured.');
        }

        $raw = @file_get_contents(self::JWKS_URI);
        if ($raw === false || $raw === '') {
            throw new \RuntimeException('Unable to fetch Apple JWKS.');
        }

        /** @var array<string, mixed> $jwks */
        $jwks = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        JWT::$leeway = 120;

        $decoded = JWT::decode($jwt, JWK::parseKeySet($jwks));
        /** @var array<string, mixed> $c */
        $c = (array) $decoded;

        if (($c['iss'] ?? '') !== 'https://appleid.apple.com') {
            throw new \UnexpectedValueException('Invalid Apple token issuer.');
        }

        if ((string) ($c['aud'] ?? '') !== $expectedAudience) {
            throw new \UnexpectedValueException('Apple token audience does not match configured client id.');
        }

        $sub = (string) ($c['sub'] ?? '');
        if ($sub === '') {
            throw new \UnexpectedValueException('Apple token missing subject.');
        }

        $email = isset($c['email']) ? strtolower(trim((string) $c['email'])) : null;
        if ($email === '') {
            $email = null;
        }

        $ev = $c['email_verified'] ?? false;
        $verified = $ev === true || $ev === 1 || $ev === 'true' || $ev === '1';

        return [
            'sub' => $sub,
            'email' => $email,
            'email_verified' => $verified,
        ];
    }
}
