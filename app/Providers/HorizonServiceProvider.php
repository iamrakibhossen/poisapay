<?php

namespace App\Providers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
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
     * Register the Horizon gate.
     *
     * Horizon runs behind the operator console: access is granted to an
     * authenticated admin (the `admin` guard) holding the system-health
     * permission, or any super-admin. Consumers on the web guard never qualify.
     */
    protected function gate(): void
    {
        Gate::define('viewHorizon', function () {
            $admin = Auth::guard('admin')->user();

            return $admin !== null
                && ($admin->can('view-system-health') || $admin->hasRole('super-admin'));
        });
    }
}
