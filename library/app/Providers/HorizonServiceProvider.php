<?php

namespace App\Providers;

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

        // IMPORTANT — Horizon's default auth callback is
        // `app()->environment('local')`, which means the `viewHorizon` gate
        // below is *bypassed entirely* in dev and ANY user (even anonymous)
        // can reach /horizon. Replace the callback so the gate fires in
        // every environment: only authenticated users whose role passes
        // `viewHorizon` get in.
        Horizon::auth(
            fn ($request) => $request->user() !== null
                && Gate::check('viewHorizon', [$request->user()]),
        );

        // Horizon::routeSmsNotificationsTo('15556667777');
        // Horizon::routeMailNotificationsTo('example@example.com');
        // Horizon::routeSlackNotificationsTo('slack-webhook-url', '#channel');
    }

    /**
     * Register the Horizon gate. Enforced in every environment via the
     * Horizon::auth() override above — admins only.
     */
    protected function gate(): void
    {
        Gate::define(
            'viewHorizon',
            fn(User $user) => $user->roles()->where('name', 'admin')->exists()
        );
    }
}
