<?php

declare(strict_types=1);

use App\Http\Controllers\AuthorController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MediaController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', fn () => Inertia::render('Welcome', [
    'canLogin' => Route::has('login'),
    'canRegister' => Route::has('register'),
    'laravelVersion' => Application::VERSION,
    'phpVersion' => PHP_VERSION,
]))->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    /*
    |--------------------------------------------------------------------------
    | Media (front controller — currently `book`, extendable to movie/music/…)
    |--------------------------------------------------------------------------
    |
    | Web routes are read-only: they render the Inertia index page, expose the
    | JSON search endpoint, and stream file downloads. All mutations
    | (store/update/delete) live in routes/api.php and are called by the SPA
    | from the modal forms hosted on the index page.
    |
    | The {type} segment selects which Domain service handles the request.
    | The `whereIn` constraint also keeps these routes from swallowing the
    | author URLs below — only known media types are matched.
    |
    */
    Route::prefix('{type}')
        // Allow every type registered in config/media.php — adding 'movie'
        // there is enough to enable /movie, /movie/search, /movie/{id}/download.
        ->whereIn('type', array_keys((array) config('media.types', [])))
        ->group(function (): void {
            // Search must be declared *before* the {id} routes so `/book/search`
            // isn't captured by `/{type}/{id}/...`.
            Route::get('search', [MediaController::class, 'search'])
                ->middleware('can:media.view,type')
                ->name('media.search');

            Route::get('/', [MediaController::class, 'index'])
                ->middleware('can:media.view,type')
                ->name('media.index');

            Route::get('{id}/download', [MediaController::class, 'download'])
                ->middleware('can:media.download,type')
                ->name('media.download');
        });

    /*
    |--------------------------------------------------------------------------
    | Authors  (Authors are shared across all media types)
    |--------------------------------------------------------------------------
    */
    Route::get('authors/search', [AuthorController::class, 'search'])
        ->middleware('can:authors.view')
        ->name('authors.search');

    Route::get('authors', [AuthorController::class, 'index'])
        ->middleware('can:authors.view')
        ->name('authors.index');
});

// Fortify auth routes (login, register, password reset, etc.) are registered
// automatically by Laravel\Fortify\FortifyServiceProvider — no manual require.
