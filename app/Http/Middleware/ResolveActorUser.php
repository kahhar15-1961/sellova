<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Symfony\Component\HttpFoundation\Request;

/**
 * Dev-oriented actor resolution: {@code Authorization: Bearer dev-user-{id}}.
 */
final class ResolveActorUser
{
    public function __invoke(Request $request): void
    {
        $auth = (string) $request->headers->get('Authorization', '');
        if (preg_match('#^Bearer\s+dev-user-(\d+)\s*$#i', $auth, $m) !== 1) {
            return;
        }

        $user = User::query()->find((int) $m[1]);
        if ($user !== null) {
            $request->attributes->set('actor', $user);
        }
    }
}
