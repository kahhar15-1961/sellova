<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use App\Models\UserAuthToken;
use Symfony\Component\HttpFoundation\Request;

/**
 * Resolves {@code actor} from {@code Authorization: Bearer ...}: dev tokens {@code dev-user-{id}}
 * or opaque access tokens stored in {@see UserAuthToken}.
 */
final class ResolveActorUser
{
    public function __invoke(Request $request): void
    {
        $auth = (string) $request->headers->get('Authorization', '');
        if (preg_match('#^Bearer\s+(\S+)\s*$#i', $auth, $m) !== 1) {
            return;
        }

        $token = $m[1];
        if ($token === '') {
            return;
        }

        if (preg_match('#^dev-user-(\d+)$#i', $token, $dm) === 1) {
            $user = User::query()->with('roles')->find((int) $dm[1]);
            if ($user !== null) {
                $request->attributes->set('actor', $user);
            }

            return;
        }

        $hash = hash('sha256', $token);
        $row = UserAuthToken::query()
            ->where('token_hash', $hash)
            ->where('kind', UserAuthToken::KIND_ACCESS)
            ->whereNull('revoked_at')
            ->first();

        if ($row === null || $row->expires_at->isPast()) {
            return;
        }

        $user = User::query()->with('roles')->find((int) $row->user_id);
        if ($user !== null && $user->status === 'active') {
            $request->attributes->set('actor', $user);
        }
    }
}
