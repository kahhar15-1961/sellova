<?php

declare(strict_types=1);

namespace App\Services\Auth;

use Firebase\JWT\JWT;
use Firebase\JWT\JWK;

/**
 * Verifies a Google Sign-In ID token (JWT) against Google's JWKS.
 */
final class GoogleIdTokenVerifier
{
    private const JWKS_URI = 'https://www.googleapis.com/oauth2/v3/certs';

    /**
     * @param  list<string>  $allowedAudiences  One or more OAuth client IDs (iOS / Android / Web) that may appear as {@code aud}.
     * @return array{sub: string, email: string, email_verified: bool, name: ?string}
     */
    public static function verify(string $jwt, array $allowedAudiences): array
    {
        $allowedAudiences = array_values(array_filter(array_map(trim(...), $allowedAudiences), static fn (string $s): bool => $s !== ''));
        if ($allowedAudiences === []) {
            throw new \InvalidArgumentException('No Google OAuth client IDs configured.');
        }

        $raw = @file_get_contents(self::JWKS_URI);
        if ($raw === false || $raw === '') {
            throw new \RuntimeException('Unable to fetch Google JWKS.');
        }

        /** @var array<string, mixed> $jwks */
        $jwks = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        JWT::$leeway = 120;

        $decoded = JWT::decode($jwt, JWK::parseKeySet($jwks));
        /** @var array<string, mixed> $c */
        $c = (array) $decoded;

        $iss = (string) ($c['iss'] ?? '');
        if ($iss !== 'https://accounts.google.com' && $iss !== 'accounts.google.com') {
            throw new \UnexpectedValueException('Invalid Google token issuer.');
        }

        $aud = $c['aud'] ?? null;
        $audiences = is_array($aud) ? $aud : [$aud];
        $audiences = array_map(static fn ($v): string => (string) $v, $audiences);
        $audOk = false;
        foreach ($allowedAudiences as $allowed) {
            if (in_array($allowed, $audiences, true)) {
                $audOk = true;
                break;
            }
        }
        if (! $audOk) {
            throw new \UnexpectedValueException('Google token audience does not match configured client IDs.');
        }

        $email = strtolower(trim((string) ($c['email'] ?? '')));
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \UnexpectedValueException('Google token did not include a valid email.');
        }

        $ev = $c['email_verified'] ?? false;
        $verified = $ev === true || $ev === 1 || $ev === 'true' || $ev === '1';

        return [
            'sub' => (string) ($c['sub'] ?? ''),
            'email' => $email,
            'email_verified' => $verified,
            'name' => isset($c['name']) ? (string) $c['name'] : null,
        ];
    }
}
