<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Telescope\IncomingEntry;
use Laravel\Telescope\Telescope;
use Laravel\Telescope\TelescopeApplicationServiceProvider;

class TelescopeServiceProvider extends TelescopeApplicationServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Telescope::night();

        $this->hideSensitiveRequestDetails();

        $isLocal = $this->app->environment('local');

        Telescope::filter(function (IncomingEntry $entry) use ($isLocal) {
            return $isLocal ||
                   $entry->isReportableException() ||
                   $entry->isFailedRequest() ||
                   $entry->isFailedJob() ||
                   $entry->isScheduledTask() ||
                   $entry->hasMonitoredTag();
        });
    }

    /**
     * Prevent sensitive request details from being logged by Telescope.
     */
    protected function hideSensitiveRequestDetails(): void
    {
        if ($this->app->environment('local')) {
            return;
        }

        Telescope::hideRequestParameters(['_token']);

        Telescope::hideRequestHeaders([
            'cookie',
            'x-csrf-token',
            'x-xsrf-token',
        ]);
    }

    /**
     * Register the Telescope gate.
     *
     * Enforced in every environment via the authorization() override below
     * — admins only.
     */
    protected function gate(): void
    {
        Gate::define(
            'viewTelescope',
            fn(User $user) => $user->roles()->where('name', 'admin')->exists()
        );
    }

    /**
     * Override the parent's `authorization()` so the role gate runs in every
     * environment.
     *
     * The default Telescope::auth() callback short-circuits to "allowed"
     * whenever APP_ENV=local — `return app()->environment('local') ||
     * Gate::check('viewTelescope', [$request->user()])`. That means any
     * authenticated (or even unauthenticated) user can reach /telescope in
     * dev, bypassing the role check entirely. This override drops the
     * environment shortcut and requires the gate to pass everywhere.
     */
    protected function authorization(): void
    {
        $this->gate();

        Telescope::auth(
            fn ($request) => $request->user() !== null
                && Gate::check('viewTelescope', [$request->user()]),
        );
    }
}
