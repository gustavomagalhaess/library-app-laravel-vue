<?php

declare(strict_types=1);

use App\Http\Controllers\AuthorController;
use App\Http\Controllers\JobsController;
use App\Http\Controllers\MediaController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| REST endpoints called by the SPA for mutations (create / update / delete
| / download) — all of which are now queued and return 202 + a TrackedJob
| descriptor. Reads continue to live in routes/web.php (Inertia + search).
|
| Authentication is via the Sanctum stateful guard (see bootstrap/app.php),
| so the SPA reuses the same session cookie it already has from the
| Inertia-rendered login flow — no API tokens required.
|
| Authorization mirrors the web routes:
|   - media.* gates  → resolved by MediaPolicy (type-aware)
|   - authors.* perms → resolved directly by Spatie
|
*/

Route::middleware(['auth:sanctum', 'verified'])->group(function (): void {
    /*
    |--------------------------------------------------------------------------
    | Media (front controller — currently `book`, extendable to movie/music/…)
    |--------------------------------------------------------------------------
    |
    | Note: update is POST (not PUT) because multipart/form-data file uploads
    | can't be sent over PUT from the browser; Laravel's _method tunneling is
    | a web-stack convenience that we avoid here for clarity. The HTTP verb
    | per resource is documented in the README.
    */
    Route::prefix('{type}')
        // Same source of truth as routes/web.php — config/media.php is the
        // only place the supported type list is hard-coded.
        ->whereIn('type', array_keys((array) config('media.types', [])))
        ->group(function (): void {
            Route::post('/', [MediaController::class, 'store'])
                ->middleware('can:media.create,type')
                ->name('api.media.store');

            Route::post('{id}', [MediaController::class, 'update'])
                ->middleware('can:media.update,type')
                ->name('api.media.update');

            Route::delete('{id}', [MediaController::class, 'destroy'])
                ->middleware('can:media.delete,type')
                ->name('api.media.destroy');

            // Queued download preparation. The SPA polls /api/jobs/{id} and
            // follows result.url once the job resolves to a signed URL.
            Route::post('{id}/download', [MediaController::class, 'requestDownload'])
                ->middleware('can:media.download,type')
                ->name('api.media.download.request');
        });

    /*
    |--------------------------------------------------------------------------
    | Authors
    |--------------------------------------------------------------------------
    */
    Route::post('authors', [AuthorController::class, 'store'])
        ->middleware('can:authors.create')
        ->name('api.authors.store');

    Route::put('authors/{author}', [AuthorController::class, 'update'])
        ->middleware('can:authors.update')
        ->name('api.authors.update');

    Route::delete('authors/{author}', [AuthorController::class, 'destroy'])
        ->middleware('can:authors.delete')
        ->name('api.authors.destroy');

    /*
    |--------------------------------------------------------------------------
    | Job tracking (polled by the SPA to reconcile optimistic UI)
    |--------------------------------------------------------------------------
    */
    Route::get('jobs/{uuid}', [JobsController::class, 'show'])
        ->name('api.jobs.show');
});
