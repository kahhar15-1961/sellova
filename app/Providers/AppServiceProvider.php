<?php

declare(strict_types=1);

namespace App\Providers;

use App\Admin\AdminPermission;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

final class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        foreach (AdminPermission::all() as $code) {
            Gate::define($code, static fn (\App\Models\User $user): bool => $user->hasPermissionCode($code));
        }
    }
}
