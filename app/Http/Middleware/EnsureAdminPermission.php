<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Admin\AdminAuthorizer;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route middleware: {@code admin.permission:admin.users.view} or comma-separated OR list.
 */
final class EnsureAdminPermission
{
    public function handle(Request $request, Closure $next, string $permissionCsv): Response
    {
        $user = $request->user();
        if ($user === null) {
            abort(Response::HTTP_FORBIDDEN);
        }

        $codes = array_values(array_filter(array_map(trim(...), explode(',', $permissionCsv))));
        if ($codes === [] || ! AdminAuthorizer::userHasAnyPermission($user, $codes)) {
            abort(Response::HTTP_FORBIDDEN, 'Missing required permission.');
        }

        return $next($request);
    }
}
