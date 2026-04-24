<?php

namespace App\Providers;

use App\Auth\RoleCodes;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Horizon\Horizon;
use Laravel\Horizon\HorizonApplicationServiceProvider;

class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        parent::boot();

        // Horizon::routeSmsNotificationsTo('15556667777');
        // Horizon::routeMailNotificationsTo('example@example.com');
        // Horizon::routeSlackNotificationsTo('slack-webhook-url', '#channel');
    }

    /**
     * @see HorizonApplicationServiceProvider::authorization()
     */
    protected function authorization(): void
    {
        $this->gate();

        Horizon::auth(function ($request): bool {
            if (Gate::check('viewHorizon', [$request->user()])) {
                return true;
            }

            return app()->environment('local')
                && (bool) config('horizon.allow_local_unauthenticated', false);
        });
    }

    /**
     * Register the Horizon gate.
     *
     * This gate determines who can access Horizon in non-local environments.
     */
    protected function gate(): void
    {
        Gate::define('viewHorizon', function ($user = null): bool {
            if (! $user instanceof User) {
                return false;
            }

            return $user->hasRoleCode(RoleCodes::SuperAdmin) || $user->hasRoleCode(RoleCodes::Admin);
        });
    }
}
