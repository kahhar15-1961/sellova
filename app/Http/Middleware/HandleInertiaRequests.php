<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Admin\AdminAuthorizer;
use Illuminate\Http\Request;
use Inertia\Middleware;

final class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    /**
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return array_merge(parent::share($request), [
            'auth' => [
                'user' => $user === null ? null : [
                    'id' => $user->id,
                    'email' => $user->email,
                    'name' => $user->email ?? ('User #'.$user->id),
                ],
            ],
            'admin' => [
                'roles' => $user === null ? [] : AdminAuthorizer::roleCodesForUser($user),
                'permissions' => $user === null ? [] : AdminAuthorizer::permissionCodesForUser($user),
            ],
            'can' => $user === null ? [] : AdminAuthorizer::permissionBooleanMap($user),
            'filters' => [
                'query' => $request->query(),
            ],
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
            ],
        ]);
    }
}
