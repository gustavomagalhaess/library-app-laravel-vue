<?php

declare(strict_types=1);

namespace App\Providers;

use App\Policies\MediaPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

/**
 * Registers application Gates.
 *
 * The `media.*` gates back the `can:media.action,type` middleware applied
 * to every media route — they receive the user and the route's {type}
 * parameter, then delegate to {@see MediaPolicy} for the actual decision.
 * This keeps the routes self-documenting while the permission rules live
 * in one place.
 *
 * Spatie's permission package already exposes each seeded permission
 * (books.view, authors.update, …) as a Gate, so the author and other
 * non-polymorphic routes can keep using `can:authors.view` directly.
 */
class AuthServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Gate::define('media.view',     [MediaPolicy::class, 'view']);
        Gate::define('media.create',   [MediaPolicy::class, 'create']);
        Gate::define('media.update',   [MediaPolicy::class, 'update']);
        Gate::define('media.delete',   [MediaPolicy::class, 'delete']);
        Gate::define('media.download', [MediaPolicy::class, 'download']);
    }
}
